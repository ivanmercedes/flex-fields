<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Http\FlexApi;
use IvanMercedes\FlexFields\Http\Resources\RecordResource;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CRUD endpoints for entity records. All business rules live in RecordDomainService.
 */
class RecordApiController extends Controller
{
    public function __construct(protected RecordDomainService $records) {}

    /**
     * GET /api/flex/{entity}?status=&search=&category=&per_page=&page=&trashed=with|only
     */
    public function index(Request $request, string $entity): ResourceCollection
    {
        FlexApi::authorizeToken($request, 'read');
        $model = FlexApi::entity($request, $entity);
        FlexApi::authorize($request, FlexPermissions::VIEW_ANY, $model);

        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(RecordDomainService::STATUSES)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'trashed' => ['sometimes', 'nullable', Rule::in(['with', 'only'])],
        ]);

        if (! empty($validated['trashed'])) {
            FlexApi::authorize($request, FlexPermissions::RESTORE, $model);
        }

        $perPage = min(
            (int) ($validated['per_page'] ?? config('flex-fields.api.per_page', 25)),
            (int) config('flex-fields.api.max_per_page', 100),
        );

        $paginator = $this->records->paginate(
            $model->id,
            [
                'status' => $validated['status'] ?? null,
                'search' => $validated['search'] ?? null,
                'category_id' => $validated['category'] ?? null,
                'trashed' => $validated['trashed'] ?? null,
            ],
            $perPage,
            (int) ($validated['page'] ?? 1),
            $model->tenant_id,
        );

        return $this->resource()::collection($paginator->withQueryString());
    }

    /**
     * GET /api/flex/{entity}/{record}
     */
    public function show(Request $request, string $entity, string $record): JsonResource
    {
        FlexApi::authorizeToken($request, 'read');
        $model = FlexApi::entity($request, $entity);
        FlexApi::authorize($request, FlexPermissions::VIEW, $model);

        return $this->makeResource($this->findRecord($model, $record));
    }

    /**
     * POST /api/flex/{entity}
     * Body: { title, slug?, status?, order?, categories?: [], values: { field_key: value } }
     */
    public function store(Request $request, string $entity): JsonResponse
    {
        FlexApi::authorizeToken($request, 'write');
        $model = FlexApi::entity($request, $entity);
        FlexApi::authorize($request, FlexPermissions::CREATE, $model);

        $validated = $this->validatePayload($request, creating: true);
        $canPublish = FlexPermissions::allows($request->user(), FlexPermissions::PUBLISH, $model);

        $validated['status'] ??= $canPublish ? 'published' : 'draft';

        if ($validated['status'] === 'published' && ! $canPublish) {
            FlexApi::authorize($request, FlexPermissions::PUBLISH, $model);
        }

        $record = $this->domain(fn () => $this->records->create(
            $model->id,
            array_intersect_key($validated, array_flip(['title', 'slug', 'status', 'order'])),
            $validated['values'] ?? [],
            $validated['categories'] ?? [],
            $model->tenant_id,
        ));

        return $this->makeResource($record)->response()->setStatusCode(201);
    }

    /**
     * PUT/PATCH /api/flex/{entity}/{record}
     */
    public function update(Request $request, string $entity, string $record): JsonResource
    {
        FlexApi::authorizeToken($request, 'write');
        $model = FlexApi::entity($request, $entity);
        FlexApi::authorize($request, FlexPermissions::UPDATE, $model);

        $existing = $this->findRecord($model, $record);
        $validated = $this->validatePayload($request, creating: false);

        if (isset($validated['status']) && $validated['status'] !== $existing->status
            && in_array('published', [$validated['status'], $existing->status], true)) {
            FlexApi::authorize($request, FlexPermissions::PUBLISH, $model);
        }

        $updated = $this->domain(fn () => $this->records->update(
            $model->id,
            $existing->id,
            array_intersect_key($validated, array_flip(['title', 'slug', 'status', 'order'])),
            $validated['values'] ?? [],
            array_key_exists('categories', $validated) ? (array) $validated['categories'] : null,
            $model->tenant_id,
        ));

        return $this->makeResource($updated);
    }

    /**
     * DELETE /api/flex/{entity}/{record}?force=1
     */
    public function destroy(Request $request, string $entity, string $record): JsonResponse
    {
        FlexApi::authorizeToken($request, 'delete');
        $model = FlexApi::entity($request, $entity);

        $force = $request->boolean('force');
        FlexApi::authorize($request, $force ? FlexPermissions::FORCE_DELETE : FlexPermissions::DELETE, $model);

        $existing = $this->findRecord($model, $record, withTrashed: $force);
        $this->domain(fn () => $this->records->delete($model->id, $existing->id, $force, $model->tenant_id));

        return response()->json(['message' => $force ? 'Record permanently deleted.' : 'Record deleted.']);
    }

    /**
     * POST /api/flex/{entity}/{record}/restore
     */
    public function restore(Request $request, string $entity, string $record): JsonResource
    {
        FlexApi::authorizeToken($request, 'write');
        $model = FlexApi::entity($request, $entity);
        FlexApi::authorize($request, FlexPermissions::RESTORE, $model);

        $existing = $this->findRecord($model, $record, withTrashed: true);

        return $this->makeResource($this->records->restore($model->id, $existing->id, $model->tenant_id));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, bool $creating): array
    {
        // Accept "data" as an alias of "values" for symmetry with the JSON output.
        if (! $request->has('values') && is_array($request->input('data'))) {
            $request->merge(['values' => $request->input('data')]);
        }

        return $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(RecordDomainService::STATUSES)],
            'order' => ['sometimes', 'integer'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['nullable'],
            'values' => ['sometimes', 'array'],
        ]);
    }

    protected function findRecord(Entity $entity, string $identifier, bool $withTrashed = false): EntityRecord
    {
        $record = $this->records->find($entity->id, $identifier, $entity->tenant_id, $withTrashed);

        if (! $record) {
            throw new HttpException(404, "Record [{$identifier}] not found.");
        }

        return $record;
    }

    /**
     * Run a domain operation, turning business-rule violations into 422 responses.
     */
    protected function domain(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RuntimeException $e) {
            throw new HttpException(422, $e->getMessage(), $e);
        }
    }

    /**
     * @return class-string<RecordResource>
     */
    protected function resource(): string
    {
        return config('flex-fields.api.resources.record', RecordResource::class);
    }

    protected function makeResource(EntityRecord $record): JsonResource
    {
        $resource = $this->resource();

        return new $resource($record->loadMissing(['entity', 'categories', 'fieldValues.customField']));
    }
}
