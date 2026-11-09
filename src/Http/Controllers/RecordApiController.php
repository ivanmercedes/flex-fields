<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Http\Controllers;

use Illuminate\Database\QueryException;
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
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\RecordPreview;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CRUD endpoints for entity records. All business rules live in RecordDomainService.
 */
class RecordApiController extends Controller
{
    /**
     * Statuses that make a record public now or later: setting or leaving
     * them requires the "publish" permission.
     */
    protected const PUBLISHING_STATUSES = ['published', RecordDomainService::SCHEDULED];

    public function __construct(protected RecordDomainService $records) {}

    /**
     * GET /api/flex/{entity}?status=&search=&category=&per_page=&page=&trashed=with|only
     *     &filter[field][operator]=value&sort=-field,title&locale=&fields=&include=
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
            'filter' => ['sometimes', 'array'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...$this->presentationRules(),
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
                'where' => $validated['filter'] ?? [],
                'sort' => $validated['sort'] ?? null,
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
        $request->validate($this->presentationRules());

        return $this->makeResource($this->findRecord($model, $record));
    }

    /**
     * GET /api/flex/_preview/{token} — the record of a RecordPreview token,
     * whatever its status, for the tenant of the request.
     */
    public function preview(Request $request, string $token): JsonResource
    {
        FlexApi::authorizeToken($request, 'read');
        $request->validate($this->presentationRules());

        $record = RecordPreview::resolve($token);
        $tenant = FlexApi::resolveTenant($request);

        if (! $record || ($tenant !== null && (int) $record->tenant_id !== $tenant)) {
            throw new HttpException(404, 'The preview link is invalid or has expired.');
        }

        $model = FlexApi::entity($request, (string) $record->entity_id);
        FlexApi::authorize($request, FlexPermissions::VIEW, $model);

        return $this->makeResource($record);
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

        if (in_array($validated['status'], self::PUBLISHING_STATUSES, true) && ! $canPublish) {
            FlexApi::authorize($request, FlexPermissions::PUBLISH, $model);
        }

        $record = $this->domain(fn () => $this->records->create(
            $model->id,
            array_intersect_key($validated, array_flip(['title', 'slug', 'status', 'publish_at', 'order'])),
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

        $changesStatus = isset($validated['status']) && $validated['status'] !== $existing->status
            && array_intersect(self::PUBLISHING_STATUSES, [$validated['status'], $existing->status]) !== [];
        $reschedules = array_key_exists('publish_at', $validated)
            && in_array(RecordDomainService::SCHEDULED, [$validated['status'] ?? null, $existing->status], true);

        if ($changesStatus || $reschedules) {
            FlexApi::authorize($request, FlexPermissions::PUBLISH, $model);
        }

        $updated = $this->domain(fn () => $this->records->update(
            $model->id,
            $existing->id,
            array_intersect_key($validated, array_flip(['title', 'slug', 'status', 'publish_at', 'order'])),
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
     * Query options that change how records are presented (see RecordResource).
     *
     * @return array<string, mixed>
     */
    protected function presentationRules(): array
    {
        return [
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(FieldTypes::locales())],
            'fields' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'include' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
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
            'publish_at' => ['sometimes', 'nullable', 'date'],
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
        } catch (QueryException $e) {
            // Database errors carry SQL and connection details: report them, never show them.
            report($e);

            throw new HttpException(422, 'The record could not be saved.', $e);
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
