<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Support\ConditionEvaluator;
use IvanMercedes\FlexFields\Support\FieldCache;
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\FlexSchema;
use IvanMercedes\FlexFields\Support\Keys;
use IvanMercedes\FlexFields\Support\ValidationRules;
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
        $validTypes = FieldTypes::all();
        if (! empty($validTypes) && ! in_array($type, $validTypes, true)) {
            throw new RuntimeException("Invalid field type [{$type}]. Supported types: " . implode(', ', $validTypes));
        }

        $key = Keys::make(! empty($attributes['key']) ? (string) $attributes['key'] : $label);

        $originalKey = $key;
        $counter = 1;
        while ($entity->customFields()->where('key', $key)->exists()) {
            $key = "{$originalKey}_{$counter}";
            $counter++;
        }

        $maxOrder = (int) ($entity->customFields()->max('order') ?? 0);

        $settings = $this->normalizeSettings($entity, $type, (array) ($attributes['settings'] ?? []), $key);

        // Resolved before creating so an unknown group does not leave a half-created field.
        $group = FlexSchema::hasFieldGroups()
            ? ['field_group_id' => $this->resolveGroupReference($entity, $attributes)]
            : [];

        $field = $entity->customFields()->create($group + [
            'tenant_id' => $entity->tenant_id,
            'label' => $label,
            'key' => $key,
            'type' => $type,
            'description' => $attributes['description'] ?? null,
            'placeholder' => $attributes['placeholder'] ?? null,
            'default_value' => $attributes['default_value'] ?? null,
            'options' => $this->normalizeOptions($attributes['options'] ?? null),
            'validation_rules' => $this->sanitizeRules($attributes['validation_rules'] ?? []),
            'settings' => $settings,
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
                    $newKey = Keys::make((string) $attributes['key']);
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
                    $validTypes = FieldTypes::all();
                    if (! empty($validTypes) && ! in_array($type, $validTypes, true)) {
                        throw new RuntimeException("Invalid field type [{$type}]. Supported types: " . implode(', ', $validTypes));
                    }
                    $data['type'] = $type;
                } elseif ($k === 'category_ids') {
                    $data['category_ids'] = is_array($attributes['category_ids'])
                        ? $this->resolveCategoryIds($entity, $attributes['category_ids'])
                        : null;
                } elseif ($k === 'options') {
                    $data['options'] = $this->normalizeOptions($attributes['options']);
                } elseif ($k === 'validation_rules') {
                    $data['validation_rules'] = $this->sanitizeRules($attributes['validation_rules']);
                } elseif ($k === 'settings') {
                    // Settings are merged so partial updates (e.g. only show_when) keep the rest.
                    $merged = array_merge($field->settings ?? [], (array) $attributes['settings']);
                    $data['settings'] = array_filter($merged, fn ($v) => $v !== null);
                } else {
                    $data[$k] = $attributes[$k];
                }
            }
        }

        if (FlexSchema::hasFieldGroups() && (array_key_exists('group', $attributes) || array_key_exists('field_group_id', $attributes))) {
            $data['field_group_id'] = $this->resolveGroupReference($entity, $attributes);
        }

        if (array_key_exists('settings', $data) || array_key_exists('type', $data)) {
            $data['settings'] = $this->normalizeSettings(
                $entity,
                (string) ($data['type'] ?? $field->type),
                (array) ($data['settings'] ?? $field->settings ?? []),
                (string) ($data['key'] ?? $field->key),
            );
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
     * Validate and normalize type-specific settings (conditional visibility,
     * relationships, translations, repeater schema, address parts...).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function normalizeSettings(Entity $entity, string $type, array $settings, ?string $ownKey = null): array
    {
        if (array_key_exists('show_when', $settings)) {
            try {
                $showWhen = ConditionEvaluator::normalize($settings['show_when']);
            } catch (InvalidArgumentException $e) {
                throw new RuntimeException($e->getMessage(), previous: $e);
            }

            foreach ($showWhen['conditions'] ?? [] as $condition) {
                if ($condition['field'] === $ownKey) {
                    throw new RuntimeException('A field cannot depend on its own value in show_when.');
                }

                if (! $entity->customFields()->where('key', $condition['field'])->exists()) {
                    throw new RuntimeException("show_when references unknown field [{$condition['field']}] on entity [{$entity->slug}].");
                }
            }

            if ($showWhen === null) {
                unset($settings['show_when']);
            } else {
                $settings['show_when'] = $showWhen;
            }
        }

        if ($type === 'relationship') {
            $related = $settings['related_entity'] ?? null;

            if (empty($related)) {
                throw new RuntimeException('Relationship fields require settings.related_entity (slug or ID of the related entity).');
            }

            $relatedEntity = $this->entityService->find($related, $entity->tenant_id);

            if (! $relatedEntity) {
                throw new RuntimeException("Related entity [{$related}] not found or not accessible.");
            }

            $settings['related_entity'] = $relatedEntity->slug;
            $settings['multiple'] = (bool) ($settings['multiple'] ?? false);
        }

        if (array_key_exists('translatable', $settings)) {
            $settings['translatable'] = (bool) $settings['translatable'] && in_array($type, FieldTypes::TRANSLATABLE, true);
        }

        if ($type === 'address' && isset($settings['address_parts'])) {
            $settings['address_parts'] = array_values(array_intersect((array) $settings['address_parts'], FieldTypes::ADDRESS_PARTS));
        }

        if ($type === 'repeater' && isset($settings['schema'])) {
            $validTypes = FieldTypes::all();
            $schema = [];

            foreach ((array) $settings['schema'] as $subField) {
                if (! is_array($subField) || empty($subField['label'])) {
                    throw new RuntimeException('Each repeater sub-field requires at least a label.');
                }

                $subType = (string) ($subField['type'] ?? 'text');

                if (! in_array($subType, $validTypes, true) || in_array($subType, ['repeater', 'relationship'], true)) {
                    throw new RuntimeException("Invalid repeater sub-field type [{$subType}].");
                }

                $schema[] = [
                    'label' => (string) $subField['label'],
                    'key' => Keys::make((string) ($subField['key'] ?? $subField['label'])),
                    'type' => $subType,
                    'width' => in_array($subField['width'] ?? 'full', ['full', 'half', 'third'], true) ? ($subField['width'] ?? 'full') : 'full',
                    'is_required' => (bool) ($subField['is_required'] ?? false),
                    'options' => $subField['options'] ?? null,
                ];
            }

            $settings['schema'] = $schema;
        }

        return $settings;
    }

    /**
     * Accepts {"value": "Label"}, [{"value": "x", "label": "X"}] or ["a", "b"].
     *
     * @return array<int|string, mixed>|null
     */
    public function normalizeOptions(mixed $options): ?array
    {
        if (empty($options) || ! is_array($options)) {
            return null;
        }

        if (array_is_list($options) && ! is_array($options[0] ?? null)) {
            return array_map(fn ($value) => ['value' => (string) $value, 'label' => (string) $value], $options);
        }

        return $options;
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

    /**
     * @param  array<int, mixed>|null  $rules
     * @return array<int, string>
     */
    protected function sanitizeRules(?array $rules): array
    {
        try {
            return ValidationRules::sanitize($rules);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function resolveGroupReference(Entity $entity, array $attributes): ?int
    {
        $reference = $attributes['group'] ?? $attributes['field_group_id'] ?? null;

        return (new FieldGroupDomainService($this->entityService))->resolveGroupId($entity, $reference);
    }
}
