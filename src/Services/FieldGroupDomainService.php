<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Illuminate\Database\Eloquent\Collection;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\FieldGroup;
use IvanMercedes\FlexFields\Support\FieldCache;
use IvanMercedes\FlexFields\Support\Keys;
use RuntimeException;

/**
 * Field Groups / Sections: labeled, collapsible sections that organize the
 * custom fields of an entity's form.
 */
class FieldGroupDomainService
{
    public function __construct(
        protected EntityDomainService $entityService = new EntityDomainService
    ) {}

    /**
     * @return Collection<int, FieldGroup>
     */
    public function list(int | string $entityIdOrSlug, ?int $tenantId = null): Collection
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        return $entity->fieldGroups()->with('fields')->get();
    }

    public function find(int | string $entityIdOrSlug, int | string $groupIdOrKey, ?int $tenantId = null): ?FieldGroup
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        return $this->findInEntity($entity, $groupIdOrKey);
    }

    /**
     * @throws RuntimeException
     */
    public function findOrFail(int | string $entityIdOrSlug, int | string $groupIdOrKey, ?int $tenantId = null): FieldGroup
    {
        $group = $this->find($entityIdOrSlug, $groupIdOrKey, $tenantId);

        if (! $group) {
            throw new RuntimeException("Field group [{$groupIdOrKey}] not found on entity [{$entityIdOrSlug}].");
        }

        return $group;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int | string $entityIdOrSlug, array $attributes, ?int $tenantId = null): FieldGroup
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        $label = trim((string) ($attributes['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Field group label is required.');
        }

        $key = Keys::make((string) ($attributes['key'] ?? '') ?: $label);
        $originalKey = $key;
        $counter = 1;
        while ($entity->fieldGroups()->where('key', $key)->exists()) {
            $key = "{$originalKey}_{$counter}";
            $counter++;
        }

        $group = $entity->fieldGroups()->create([
            'tenant_id' => $entity->tenant_id,
            'label' => $label,
            'key' => $key,
            'description' => $attributes['description'] ?? null,
            'order' => (int) ($attributes['order'] ?? ((int) $entity->fieldGroups()->max('order') + 1)),
            'is_collapsible' => (bool) ($attributes['is_collapsible'] ?? true),
            'is_collapsed' => (bool) ($attributes['is_collapsed'] ?? false),
        ]);

        if (! empty($attributes['fields']) && is_array($attributes['fields'])) {
            $this->assignFields($entity->id, $group->id, $attributes['fields'], $entity->tenant_id);
        }

        return $group->fresh('fields');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(int | string $entityIdOrSlug, int | string $groupIdOrKey, array $attributes, ?int $tenantId = null): FieldGroup
    {
        $group = $this->findOrFail($entityIdOrSlug, $groupIdOrKey, $tenantId);
        $entity = $group->entity;

        $data = [];

        foreach (['label', 'description', 'order', 'is_collapsible', 'is_collapsed'] as $attribute) {
            if (array_key_exists($attribute, $attributes)) {
                $data[$attribute] = $attributes[$attribute];
            }
        }

        if (array_key_exists('key', $attributes)) {
            $newKey = Keys::make((string) $attributes['key']);

            if ($entity->fieldGroups()->where('key', $newKey)->where('id', '!=', $group->id)->exists()) {
                throw new RuntimeException("Field group key [{$newKey}] is already in use on entity [{$entity->slug}].");
            }

            $data['key'] = $newKey;
        }

        if ($data !== []) {
            $group->update($data);
        }

        if (array_key_exists('fields', $attributes) && is_array($attributes['fields'])) {
            $this->assignFields($entity->id, $group->id, $attributes['fields'], $entity->tenant_id);
        }

        return $group->fresh('fields');
    }

    /**
     * Delete a group. Its fields are kept and become ungrouped.
     */
    public function delete(int | string $entityIdOrSlug, int | string $groupIdOrKey, ?int $tenantId = null): bool
    {
        $group = $this->findOrFail($entityIdOrSlug, $groupIdOrKey, $tenantId);

        $group->fields()->update(['field_group_id' => null]);
        FieldCache::forgetForEntity($group->entity_id);

        return (bool) $group->delete();
    }

    /**
     * Move fields (by key or ID) into a group.
     *
     * @param  array<int, int|string>  $fieldIdsOrKeys
     */
    public function assignFields(int | string $entityIdOrSlug, int | string $groupIdOrKey, array $fieldIdsOrKeys, ?int $tenantId = null): FieldGroup
    {
        $group = $this->findOrFail($entityIdOrSlug, $groupIdOrKey, $tenantId);
        $entity = $group->entity;

        $numeric = array_map('intval', array_filter($fieldIdsOrKeys, 'is_numeric'));
        $keys = array_values(array_filter($fieldIdsOrKeys, fn ($v) => ! is_numeric($v)));

        $entity->customFields()
            ->where(fn ($q) => $q->whereIn('id', $numeric ?: [0])->orWhereIn('key', $keys ?: ['']))
            ->update(['field_group_id' => $group->id]);

        FieldCache::forgetForEntity($entity);

        return $group->fresh('fields');
    }

    /**
     * Resolve a group reference (ID or key) to its ID within an entity.
     *
     * @throws RuntimeException
     */
    public function resolveGroupId(Entity $entity, int | string | null $groupIdOrKey): ?int
    {
        if ($groupIdOrKey === null || $groupIdOrKey === '') {
            return null;
        }

        $group = $this->findInEntity($entity, $groupIdOrKey);

        if (! $group) {
            throw new RuntimeException("Field group [{$groupIdOrKey}] not found on entity [{$entity->slug}].");
        }

        return $group->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function format(FieldGroup $group): array
    {
        return [
            'id' => $group->id,
            'key' => $group->key,
            'label' => $group->label,
            'description' => $group->description,
            'order' => $group->order,
            'is_collapsible' => (bool) $group->is_collapsible,
            'is_collapsed' => (bool) $group->is_collapsed,
            'fields' => $group->relationLoaded('fields') ? $group->fields->pluck('key')->values()->all() : null,
        ];
    }

    protected function findInEntity(Entity $entity, int | string $groupIdOrKey): ?FieldGroup
    {
        return is_numeric($groupIdOrKey)
            ? $entity->fieldGroups()->where('id', (int) $groupIdOrKey)->first()
            : $entity->fieldGroups()->where('key', (string) $groupIdOrKey)->first();
    }
}
