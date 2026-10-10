<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\EntityDomainService;

/**
 * JSON representation of an entity and its schema (fields, groups, categories).
 *
 * @property Entity $resource
 */
class EntitySchemaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(EntityDomainService::class)->getSchema($this->resource->id, $this->resource->tenant_id);
    }
}
