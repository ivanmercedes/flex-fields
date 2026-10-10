<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\MediaLibrary;
use RuntimeException;

class RecordDomainService
{
    public const STATUSES = ['draft', 'published', 'archived'];

    public function __construct(
        protected EntityDomainService $entityService = new EntityDomainService,
        protected ?RecordValueValidator $validator = null,
        protected ?RecordHistoryService $history = null,
    ) {}

    /**
     * List records for an entity with filtering.
     *
     * @param  array{status?: ?string, search?: ?string, category_id?: ?int, limit?: ?int, offset?: ?int, trashed?: ?string}  $filters
     * @return Collection<int, EntityRecord>
     */
    public function list(int | string $entityIdOrSlug, array $filters = [], ?int $tenantId = null): Collection
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        $limit = isset($filters['limit']) ? max(1, min(100, (int) $filters['limit'])) : 25;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        return $this->query($entity, $filters)->skip($offset)->take($limit)->get();
    }

    /**
     * Paginate records for an entity with filtering.
     *
     * @param  array{status?: ?string, search?: ?string, category_id?: ?int, trashed?: ?string}  $filters
     */
    public function paginate(int | string $entityIdOrSlug, array $filters = [], int $perPage = 25, int $page = 1, ?int $tenantId = null): LengthAwarePaginator
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        return $this->query($entity, $filters)->paginate(perPage: max(1, $perPage), page: max(1, $page));
    }

    /**
     * Base query for an entity's records with the standard filters applied.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(Entity $entity, array $filters = []): HasMany | Builder
    {
        $query = $entity->records()->with(['entity', 'categories', 'fieldValues.customField']);

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

        match ($filters['trashed'] ?? null) {
            'with' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => null,
        };

        return $query->orderBy('order')->orderByDesc('id');
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
     * @param  array<int, int|string>  $categoryIds
     *
     * @throws ValidationException
     */
    public function create(
        int | string $entityIdOrSlug,
        array $attributes,
        array $fieldValues = [],
        array $categoryIds = [],
        ?int $tenantId = null
    ): EntityRecord {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        $status = (string) ($attributes['status'] ?? 'published');
        $this->ensureValidStatus($status);

        $fieldValues = $this->validator()->validate($entity, $fieldValues, creating: true);
        $categoryIds = $this->resolveCategoryIds($entity, $categoryIds);

        $title = trim((string) ($attributes['title'] ?? 'Untitled'));
        $slug = ! empty($attributes['slug'])
            ? Str::slug((string) $attributes['slug'])
            : Str::slug($title);

        $originalSlug = $slug;
        $counter = 1;
        while ($entity->records()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        $record = DB::transaction(function () use ($entity, $title, $slug, $status, $attributes, $fieldValues, $categoryIds) {
            /** @var EntityRecord $record */
            $record = $entity->records()->create([
                'tenant_id' => $entity->tenant_id,
                'title' => $title,
                'slug' => $slug,
                'status' => $status,
                'order' => (int) ($attributes['order'] ?? 0),
                'meta' => $attributes['meta'] ?? null,
            ]);

            if (! empty($fieldValues)) {
                $this->writeValues($record, $fieldValues);
            }

            if (! empty($categoryIds)) {
                $record->categories()->sync($categoryIds);
            }

            return $record;
        });

        $this->history()->record($record, RecordHistoryService::EVENT_CREATED);

        return $record->load(['entity', 'categories', 'fieldValues.customField']);
    }

    /**
     * Update an existing record.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $fieldValues
     * @param  array<int, int|string>|null  $categoryIds
     *
     * @throws ValidationException
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
                ->withTrashed()
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
            $this->ensureValidStatus($status);
            $data['status'] = $status;
        }
        if (array_key_exists('order', $attributes)) {
            $data['order'] = (int) $attributes['order'];
        }
        if (array_key_exists('meta', $attributes)) {
            $data['meta'] = $attributes['meta'];
        }

        $fieldValues = empty($fieldValues) ? [] : $this->validator()->validate($entity, $fieldValues, creating: false);
        $categoryIds = $categoryIds === null ? null : $this->resolveCategoryIds($entity, $categoryIds);

        DB::transaction(function () use ($record, $data, $fieldValues, $categoryIds) {
            if (! empty($data)) {
                $record->update($data);
            }

            if (! empty($fieldValues)) {
                $this->writeValues($record, $fieldValues);
            }

            if ($categoryIds !== null) {
                $record->categories()->sync($categoryIds);
            }
        });

        $this->history()->record($record, RecordHistoryService::EVENT_UPDATED);

        return $record->fresh(['entity', 'categories', 'fieldValues.customField']);
    }

    /**
     * Set or update custom field values on an existing record.
     *
     * @param  array<string, mixed>  $fieldValues
     *
     * @throws ValidationException
     */
    public function setValues(
        int | string $entityIdOrSlug,
        int | string $recordIdOrSlug,
        array $fieldValues,
        ?int $tenantId = null
    ): EntityRecord {
        return $this->update($entityIdOrSlug, $recordIdOrSlug, fieldValues: $fieldValues, tenantId: $tenantId);
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
     * Restore a soft-deleted record.
     */
    public function restore(int | string $entityIdOrSlug, int | string $recordIdOrSlug, ?int $tenantId = null): EntityRecord
    {
        $record = $this->findOrFail($entityIdOrSlug, $recordIdOrSlug, $tenantId, withTrashed: true);

        if ($record->trashed()) {
            $record->restore();
        }

        return $record->fresh(['entity', 'categories', 'fieldValues.customField']);
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
            'data' => array_merge($record->data, MediaLibrary::urls($record)),
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
            'deleted_at' => $record->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Persist custom field values on a record (no validation; callers validate).
     * Unknown keys are ignored.
     *
     * @param  array<string, mixed>  $fieldValues
     */
    public function writeValues(EntityRecord $record, array $fieldValues): void
    {
        $fieldsByKey = $record->entity->customFields()->get()->keyBy('key');

        foreach ($fieldValues as $key => $value) {
            $field = $fieldsByKey->get($key);
            if (! $field || MediaLibrary::handles($field)) {
                continue;
            }

            $record->fieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                [
                    'tenant_id' => $record->tenant_id,
                    'value' => FieldTypes::serialize($value),
                ]
            );
        }

        $record->unsetRelation('fieldValues');
    }

    /**
     * Only keep categories that belong to the entity (accepts IDs, slugs or names).
     *
     * @param  array<int, int|string>  $categoryIdsOrSlugs
     * @return array<int, int>
     */
    public function resolveCategoryIds(Entity $entity, array $categoryIdsOrSlugs): array
    {
        $categoryIdsOrSlugs = array_values(array_filter($categoryIdsOrSlugs, fn ($v) => $v !== null && $v !== ''));

        if ($categoryIdsOrSlugs === []) {
            return [];
        }

        $numeric = array_map('intval', array_filter($categoryIdsOrSlugs, 'is_numeric'));
        $strings = array_values(array_filter($categoryIdsOrSlugs, fn ($v) => ! is_numeric($v)));

        $resolved = $entity->categories()
            ->where(function (Builder $q) use ($numeric, $strings) {
                $q->whereIn('id', $numeric ?: [0]);

                if ($strings !== []) {
                    $q->orWhereIn('slug', $strings)->orWhereIn('name', $strings);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($resolved) < count(array_unique($categoryIdsOrSlugs))) {
            throw new RuntimeException('One or more categories do not exist on entity [' . $entity->slug . '].');
        }

        return $resolved;
    }

    protected function ensureValidStatus(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new RuntimeException("Invalid status [{$status}]. Allowed values: " . implode(', ', self::STATUSES) . '.');
        }
    }

    protected function validator(): RecordValueValidator
    {
        return $this->validator ??= app(RecordValueValidator::class);
    }

    protected function history(): RecordHistoryService
    {
        return $this->history ??= app(RecordHistoryService::class);
    }
}
