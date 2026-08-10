<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use RuntimeException;

class EntityQuery
{
    protected ?Entity $entity;

    public function __construct(Entity | string | int $entity)
    {
        if ($entity instanceof Entity) {
            $this->entity = $entity;
        } elseif (is_numeric($entity)) {
            $this->entity = Entity::find($entity);
        } else {
            $this->entity = Entity::where('slug', (string) $entity)->first();
        }
    }

    /**
     * Get the resolved Entity model instance.
     */
    public function entity(): ?Entity
    {
        return $this->entity;
    }

    /**
     * Check if the entity exists.
     */
    public function exists(): bool
    {
        return $this->entity !== null;
    }

    /**
     * Get active custom fields (using cache if enabled).
     *
     * @return Collection<int, CustomField>
     */
    public function fields(): Collection
    {
        if (! $this->entity) {
            return new Collection;
        }

        return FieldCache::getForEntity($this->entity);
    }

    /**
     * Get all custom fields (active and inactive).
     *
     * @return Collection<int, CustomField>
     */
    public function allFields(): Collection
    {
        if (! $this->entity) {
            return new Collection;
        }

        return $this->entity->customFields;
    }

    /**
     * Get Eloquent query builder / HasMany relationship for records.
     */
    public function records(): HasMany | Builder
    {
        if (! $this->entity) {
            return EntityRecord::whereRaw('1 = 0');
        }

        return $this->entity->records();
    }

    /**
     * Find a record by ID or slug.
     */
    public function findRecord(string | int $idOrSlug): ?EntityRecord
    {
        if (! $this->entity) {
            return null;
        }

        if (is_numeric($idOrSlug)) {
            return $this->records()->find($idOrSlug);
        }

        return $this->records()->where('slug', $idOrSlug)->first();
    }

    /**
     * Find a record by ID or slug, or throw ModelNotFoundException.
     */
    public function findRecordOrFail(string | int $idOrSlug): EntityRecord
    {
        $record = $this->findRecord($idOrSlug);
        if (! $record) {
            throw new ModelNotFoundException("EntityRecord not found for identifier [{$idOrSlug}]");
        }

        return $record;
    }

    /**
     * Create a new record with attributes and custom field values.
     */
    public function createRecord(array $attributes, array $fieldValues = []): EntityRecord
    {
        if (! $this->entity) {
            throw new RuntimeException('Cannot create record for unresolved Entity.');
        }

        $recordData = array_merge([
            'entity_id' => $this->entity->id,
            'title' => $attributes['title'] ?? 'Untitled Record',
            'status' => $attributes['status'] ?? 'published',
        ], $attributes);

        /** @var EntityRecord $record */
        $record = $this->entity->records()->create($recordData);

        if (! empty($fieldValues)) {
            foreach ($fieldValues as $key => $val) {
                $record->setValue($key, $val);
            }
        }

        return $record->fresh();
    }

    /**
     * Update an existing record.
     */
    public function updateRecord(string | int | EntityRecord $record, array $attributes = [], array $fieldValues = []): EntityRecord
    {
        $recordModel = $record instanceof EntityRecord ? $record : $this->findRecordOrFail($record);

        if (! empty($attributes)) {
            $recordModel->update($attributes);
        }

        if (! empty($fieldValues)) {
            foreach ($fieldValues as $key => $val) {
                $recordModel->setValue($key, $val);
            }
        }

        return $recordModel->fresh();
    }

    /**
     * Delete a record.
     */
    public function deleteRecord(string | int | EntityRecord $record, bool $force = false): bool
    {
        $recordModel = $record instanceof EntityRecord ? $record : $this->findRecord($record);

        if (! $recordModel) {
            return false;
        }

        return $force ? (bool) $recordModel->forceDelete() : (bool) $recordModel->delete();
    }
}
