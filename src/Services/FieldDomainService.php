<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Support\FieldCache;
use RuntimeException;

class FieldDomainService
{
    public function __construct(
        protected EntityDomainService $entityService = new EntityDomainService
    ) {}

    /**
     * List all fields for an entity.
     *
     * @return Collection<int, CustomField>
     */
    public function list(int | string $entityIdOrSlug, ?int $tenantId = null): Collection
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        return $entity->customFields()->orderBy('order')->get();
    }

    /**
     * Find a custom field by ID or key within an entity.
     */
    public function find(int | string $entityIdOrSlug, int | string $fieldIdOrKey, ?int $tenantId = null): ?CustomField
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        if (is_numeric($fieldIdOrKey)) {
            return $entity->customFields()->where('id', (int) $fieldIdOrKey)->first();
        }

        return $entity->customFields()->where('key', (string) $fieldIdOrKey)->first();
    }

    /**
     * Find a custom field or throw an exception.
     *
     * @throws RuntimeException
     */
    public function findOrFail(int | string $entityIdOrSlug, int | string $fieldIdOrKey, ?int $tenantId = null): CustomField
    {
        $field = $this->find($entityIdOrSlug, $fieldIdOrKey, $tenantId);

        if (! $field) {
            throw new RuntimeException("Custom field [{$fieldIdOrKey}] not found on entity [{$entityIdOrSlug}].");
        }

        return $field;
    }

    /**
     * Create a new custom field on an entity.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(int | string $entityIdOrSlug, array $attributes, ?int $tenantId = null): CustomField
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        $label = trim((string) ($attributes['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Custom field label is required.');
        }

        $type = (string) ($attributes['type'] ?? 'text');
        $validTypes = array_keys(config('flex-fields.field_types', []));
        if (! empty($validTypes) && ! in_array($type, $validTypes, true)) {
            throw new RuntimeException("Invalid field type [{$type}]. Supported types: " . implode(', ', $validTypes));
        }

        $key = ! empty($attributes['key'])
            ? Str::snake((string) $attributes['key'])
            : Str::snake($label);

        $originalKey = $key;
        $counter = 1;
        while ($entity->customFields()->where('key', $key)->exists()) {
            $key = "{$originalKey}_{$counter}";
            $counter++;
        }

        $maxOrder = (int) ($entity->customFields()->max('order') ?? 0);

        $field = $entity->customFields()->create([
            'tenant_id' => $entity->tenant_id,
            'label' => $label,
            'key' => $key,
            'type' => $type,
            'description' => $attributes['description'] ?? null,
            'placeholder' => $attributes['placeholder'] ?? null,
            'default_value' => $attributes['default_value'] ?? null,
            'options' => $attributes['options'] ?? null,
            'validation_rules' => $attributes['validation_rules'] ?? [],
            'settings' => $attributes['settings'] ?? [],
            'order' => (int) ($attributes['order'] ?? ($maxOrder + 1)),
            'is_required' => (bool) ($attributes['is_required'] ?? false),
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'is_searchable' => (bool) ($attributes['is_searchable'] ?? false),
            'is_shown_in_list' => (bool) ($attributes['is_shown_in_list'] ?? false),
            'width' => (string) ($attributes['width'] ?? 'full'),
            'category_ids' => isset($attributes['category_ids']) && is_array($attributes['category_ids'])
                ? $this->resolveCategoryIds($entity, $attributes['category_ids'])
                : null,
        ]);

        FieldCache::forgetForEntity($entity);

        return $field;
    }

    /**
     * Update an existing custom field.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int | string $entityIdOrSlug, int | string $fieldIdOrKey, array $attributes, ?int $tenantId = null): CustomField
    {
        $field = $this->findOrFail($entityIdOrSlug, $fieldIdOrKey, $tenantId);
        $entity = $field->entity;

        $allowed = [
            'label',
            'key',
            'type',
            'description',
            'placeholder',
            'default_value',
            'options',
            'validation_rules',
            'settings',
            'order',
            'is_required',
            'is_active',
            'is_searchable',
            'is_shown_in_list',
            'width',
            'category_ids',
        ];

        $data = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $attributes)) {
                if ($k === 'key') {
                    $newKey = Str::snake((string) $attributes['key']);
                    $exists = $entity->customFields()
                        ->where('key', $newKey)
                        ->where('id', '!=', $field->id)
                        ->exists();

                    if ($exists) {
                        throw new RuntimeException("Field key [{$newKey}] is already in use on entity [{$entity->slug}].");
                    }
                    $data['key'] = $newKey;
                } elseif ($k === 'type') {
                    $type = (string) $attributes['type'];
                    $validTypes = array_keys(config('flex-fields.field_types', []));
                    if (! empty($validTypes) && ! in_array($type, $validTypes, true)) {
                        throw new RuntimeException("Invalid field type [{$type}]. Supported types: " . implode(', ', $validTypes));
                    }
                    $data['type'] = $type;
                } elseif ($k === 'category_ids') {
                    $data['category_ids'] = is_array($attributes['category_ids'])
                        ? $this->resolveCategoryIds($entity, $attributes['category_ids'])
                        : null;
                } else {
                    $data[$k] = $attributes[$k];
                }
            }
        }

        if (! empty($data)) {
            $field->update($data);
        }

        FieldCache::forgetForEntity($entity);

        return $field->fresh();
    }

    /**
     * Delete a custom field and its associated field values.
     */
    public function delete(int | string $entityIdOrSlug, int | string $fieldIdOrKey, ?int $tenantId = null): bool
    {
        $field = $this->findOrFail($entityIdOrSlug, $fieldIdOrKey, $tenantId);
        $entity = $field->entity;

        FieldCache::forgetForEntity($entity);

        $field->values()->delete();

        return (bool) $field->delete();
    }

    /**
     * Assign a custom field to categories (by ID, slug, or name).
     * Pass empty array to remove category restrictions.
     *
     * @param  array<int|string>  $categoryIdsOrSlugs
     */
    public function assignCategories(int | string $entityIdOrSlug, int | string $fieldIdOrKey, array $categoryIdsOrSlugs, ?int $tenantId = null): CustomField
    {
        return $this->update($entityIdOrSlug, $fieldIdOrKey, [
            'category_ids' => $categoryIdsOrSlugs,
        ], $tenantId);
    }

    /**
     * Resolve category IDs from an array of IDs, slugs, or names.
     *
     * @param  array<int|string>|null  $categoryIdsOrSlugs
     * @return array<int>|null
     */
    public function resolveCategoryIds(Entity $entity, ?array $categoryIdsOrSlugs): ?array
    {
        if ($categoryIdsOrSlugs === null) {
            return null;
        }

        if (empty($categoryIdsOrSlugs)) {
            return null;
        }

        $numeric = array_map('intval', array_filter($categoryIdsOrSlugs, 'is_numeric'));
        $strings = array_values(array_filter($categoryIdsOrSlugs, fn ($v) => ! is_numeric($v)));

        $resolved = $entity->categories()
            ->where(function ($q) use ($numeric, $strings) {
                if (! empty($numeric)) {
                    $q->whereIn('id', $numeric);
                }
                if (! empty($strings)) {
                    $q->orWhereIn('slug', $strings)->orWhereIn('name', $strings);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (! empty($resolved)) {
            return $resolved;
        }

        return ! empty($numeric) ? $numeric : null;
    }
}
