<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Services\RecordDomainService;

/**
 * JSON representation of a record for frontend / mobile consumers.
 *
 * Field values are exposed under "values". Add ?include=relations to expand
 * relationship fields.
 *
 * @property EntityRecord $resource
 */
class RecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = app(RecordDomainService::class)->formatRecord($this->resource);

        // Field values are exposed as "values" (same key accepted by POST/PUT).
        $data = array_merge(
            array_diff_key($data, ['data' => true, 'created_at' => true, 'updated_at' => true, 'deleted_at' => true]),
            ['values' => $data['data']],
            array_intersect_key($data, array_flip(['created_at', 'updated_at', 'deleted_at'])),
        );

        if (in_array('relations', explode(',', (string) $request->query('include', '')), true)) {
            $data['relations'] = $this->relations();
        }

        return $data;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function relations(): array
    {
        $relations = [];

        foreach ($this->resource->entity->customFields->where('type', 'relationship') as $field) {
            $relations[$field->key] = $this->resource->related($field->key)
                ->map(fn (EntityRecord $record) => [
                    'id' => $record->id,
                    'title' => $record->title,
                    'slug' => $record->slug,
                    'status' => $record->status,
                ])
                ->values()
                ->all();
        }

        return $relations;
    }
}
