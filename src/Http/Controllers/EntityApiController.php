<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Http\FlexApi;
use IvanMercedes\FlexFields\Http\Resources\EntitySchemaResource;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\EntityDomainService;

class EntityApiController extends Controller
{
    public function __construct(protected EntityDomainService $entities) {}

    /**
     * GET /api/flex/entities — entities the caller can read.
     */
    public function index(Request $request): JsonResponse
    {
        FlexApi::authorizeToken($request, 'read');

        $allowed = config('flex-fields.api.allowed_entities');

        $entities = $this->entities->list(['active_only' => true], FlexApi::resolveTenant($request))
            ->filter(fn (Entity $entity) => $allowed === null || in_array($entity->slug, (array) $allowed, true))
            ->filter(fn (Entity $entity) => FlexPermissions::allows($request->user(), FlexPermissions::VIEW_ANY, $entity))
            ->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'description' => $entity->description,
                'icon' => $entity->icon,
                'color' => $entity->color,
                'links' => [
                    'records' => url(config('flex-fields.api.prefix', 'api/flex') . '/' . $entity->slug),
                    'schema' => url(config('flex-fields.api.prefix', 'api/flex') . '/' . $entity->slug . '/schema'),
                ],
            ])
            ->values();

        return response()->json(['data' => $entities]);
    }

    /**
     * GET /api/flex/{entity}/schema — fields, groups and categories of an entity.
     */
    public function schema(Request $request, string $entity): JsonResource
    {
        FlexApi::authorizeToken($request, 'read');

        $model = FlexApi::entity($request, $entity);
        FlexApi::authorize($request, FlexPermissions::VIEW_ANY, $model);

        $resource = config('flex-fields.api.resources.entity', EntitySchemaResource::class);

        return new $resource($model);
    }
}
