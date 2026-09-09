<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\EntityRecord;
use RuntimeException;

class RecordDomainService
{
    public function __construct(
        protected EntityDomainService $entityService = new EntityDomainService
    ) {}

    /**
     * List records for an entity with filtering.
     *
     * @param  array{status?: ?string, search?: ?string, category_id?: ?int, limit?: ?int, offset?: ?int}  $filters
     * @return Collection<int, EntityRecord>
     */
    public function list(int | string $entityIdOrSlug, array $filters = [], ?int $tenantId = null): Collection
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        $query = $entity->records()->with(['categories', 'fieldValues.customField']);

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $categoryId = (int) $filters['category_id'];
            $query->whereHas('categories', fn (Builder $q) => $q->where('ff_entity_categories.id', $categoryId));
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $limit = isset($filters['limit']) ? max(1, min(100, (int) $filters['limit'])) : 25;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        return $query->orderBy('order')->orderByDesc('id')->skip($offset)->take($limit)->get();
    }

    /**
     * Find a record by ID or slug within an entity.
     */
    public function find(int | string $entityIdOrSlug, int | string $recordIdOrSlug, ?int $tenantId = null, bool $withTrashed = false): ?EntityRecord
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        $query = $entity->records()->with(['categories', 'fieldValues.customField']);

        if ($withTrashed) {
            $query->withTrashed();
        }

        if (is_numeric($recordIdOrSlug)) {
            return $query->where('id', (int) $recordIdOrSlug)->first();
        }

        return $query->where('slug', (string) $recordIdOrSlug)->first();
    }

    /**
     * Find a record or throw an exception.
     *
     * @throws RuntimeException
     */
    public function findOrFail(int | string $entityIdOrSlug, int | string $recordIdOrSlug, ?int $tenantId = null, bool $withTrashed = false): EntityRecord
    {
        $record = $this->find($entityIdOrSlug, $recordIdOrSlug, $tenantId, $withTrashed);

        if (! $record) {
            throw new RuntimeException("Record [{$recordIdOrSlug}] not found for entity [{$entityIdOrSlug}].");
        }

        return $record;
    }

    /**
     * Create a new record with custom field values and categories.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $fieldValues
     * @param  array<int, int>  $categoryIds
     */
    public function create(
        int | string $entityIdOrSlug,
        array $attributes,
        array $fieldValues = [],
        array $categoryIds = [],
        ?int $tenantId = null
    ): EntityRecord {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        $title = trim((string) ($attributes['title'] ?? 'Untitled'));
        $slug = ! empty($attributes['slug'])
            ? Str::slug((string) $attributes['slug'])
            : Str::slug($title);

        $originalSlug = $slug;
        $counter = 1;
        while ($entity->records()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $record = $entity->records()->create([
            'tenant_id' => $entity->tenant_id,
            'title' => $title,
            'slug' => $slug,
            'status' => $attributes['status'] ?? 'published',
            'order' => (int) ($attributes['order'] ?? 0),
            'meta' => $attributes['meta'] ?? null,
        ]);

        if (! empty($fieldValues)) {
            $this->applyFieldValues($record, $fieldValues);
        }

        if (! empty($categoryIds)) {
            $record->categories()->sync($categoryIds);
        }

        return $record->load(['categories', 'fieldValues.customField']);
    }

    /**
     * Update an existing record.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $fieldValues
     * @param  array<int, int>|null  $categoryIds
     */
    public function update(
        int | string $entityIdOrSlug,
        int | string $recordIdOrSlug,
        array $attributes = [],
        array $fieldValues = [],
        ?array $categoryIds = null,
        ?int $tenantId = null
    ): EntityRecord {
        $record = $this->findOrFail($entityIdOrSlug, $recordIdOrSlug, $tenantId);
        $entity = $record->entity;

        $data = [];
        if (array_key_exists('title', $attributes)) {
            $data['title'] = trim((string) $attributes['title']);
        }
        if (array_key_exists('slug', $attributes)) {
            $newSlug = Str::slug((string) $attributes['slug']);
            $exists = $entity->records()
                ->where('slug', $newSlug)
                ->where('id', '!=', $record->id)
                ->exists();

            if ($exists) {
                throw new RuntimeException("Record slug [{$newSlug}] is already in use for entity [{$entity->slug}].");
            }
            $data['slug'] = $newSlug;
        }
        if (array_key_exists('status', $attributes)) {
            $status = (string) $attributes['status'];
            if (! in_array($status, ['draft', 'published', 'archived'], true)) {
                throw new RuntimeException("Invalid status [{$status}]. Allowed values: draft, published, archived.");
            }
            $data['status'] = $status;
        }
        if (array_key_exists('order', $attributes)) {
            $data['order'] = (int) $attributes['order'];
        }
        if (array_key_exists('meta', $attributes)) {
            $data['meta'] = $attributes['meta'];
        }

        if (! empty($data)) {
            $record->update($data);
        }

        if (! empty($fieldValues)) {
            $this->applyFieldValues($record, $fieldValues);
        }

        if ($categoryIds !== null) {
            $record->categories()->sync($categoryIds);
        }

        return $record->fresh(['categories', 'fieldValues.customField']);
    }

    /**
     * Set or update custom field values on an existing record.
     *
     * @param  array<string, mixed>  $fieldValues
     */
    public function setValues(
        int | string $entityIdOrSlug,
        int | string $recordIdOrSlug,
        array $fieldValues,
        ?int $tenantId = null
    ): EntityRecord {
        $record = $this->findOrFail($entityIdOrSlug, $recordIdOrSlug, $tenantId);
        $this->applyFieldValues($record, $fieldValues);

        return $record->fresh(['categories', 'fieldValues.customField']);
    }

    /**
     * Publish a record (set status to 'published').
     */
    public function publish(int | string $entityIdOrSlug, int | string $recordIdOrSlug, ?int $tenantId = null): EntityRecord
    {
        return $this->update($entityIdOrSlug, $recordIdOrSlug, ['status' => 'published'], tenantId: $tenantId);
    }

    /**
     * Unpublish a record (set status to 'draft').
     */
    public function unpublish(int | string $entityIdOrSlug, int | string $recordIdOrSlug, ?int $tenantId = null): EntityRecord
    {
        return $this->update($entityIdOrSlug, $recordIdOrSlug, ['status' => 'draft'], tenantId: $tenantId);
    }

    /**
     * Delete a record (soft-delete by default, or force-delete).
     */
    public function delete(
        int | string $entityIdOrSlug,
        int | string $recordIdOrSlug,
        bool $force = false,
        ?int $tenantId = null
    ): bool {
        $record = $this->findOrFail($entityIdOrSlug, $recordIdOrSlug, $tenantId, withTrashed: $force);

        if ($force) {
            $record->fieldValues()->delete();
            $record->categories()->detach();

            return (bool) $record->forceDelete();
        }

        return (bool) $record->delete();
    }

    /**
     * Format a record as an array for agent or API consumption.
     *
     * @return array<string, mixed>
     */
    public function formatRecord(EntityRecord $record): array
    {
        $categories = $record->categories->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
        ])->toArray();

        return [
            'id' => $record->id,
            'entity_id' => $record->entity_id,
            'entity_slug' => $record->entity->slug ?? null,
            'title' => $record->title,
            'slug' => $record->slug,
            'status' => $record->status,
            'order' => $record->order,
            'categories' => $categories,
            'data' => $record->data,
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Apply custom field values to a record.
     *
     * @param  array<string, mixed>  $fieldValues
     */
    protected function applyFieldValues(EntityRecord $record, array $fieldValues): void
    {
        $entity = $record->entity;
        $fieldsByKey = $entity->customFields->keyBy('key');

        foreach ($fieldValues as $key => $value) {
            $field = $fieldsByKey->get($key);
            if (! $field) {
                continue;
            }

            $rawValue = is_array($value) ? json_encode($value) : $value;

            $record->fieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                [
                    'tenant_id' => $record->tenant_id,
                    'value' => $rawValue,
                ]
            );
        }
    }
}
