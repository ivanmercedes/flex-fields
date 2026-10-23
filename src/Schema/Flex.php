<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Schema;

use Closure;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Support\FlexSchema;

class Flex
{
    public static function create(string $name, Closure $callback): void
    {
        $blueprint = new Blueprint($name);
        $callback($blueprint);

        $entityAttributes = $blueprint->getEntityAttributes();

        // Use firstOrCreate to be idempotent during migrations
        $entity = Entity::firstOrCreate(
            ['slug' => $entityAttributes['slug']],
            $entityAttributes
        );

        $groupIds = static::syncFieldGroups($entity, $blueprint);

        // Process Fields
        $order = 1;
        foreach ($blueprint->getFields() as $fieldDefinition) {
            $fieldAttrs = static::resolveGroup($fieldDefinition->getAttributes(), $groupIds);
            $fieldAttrs['order'] = $order++;

            $entity->customFields()->updateOrCreate(
                ['key' => $fieldAttrs['key']],
                $fieldAttrs
            );
        }

        // Drop Fields if any
        if (! empty($blueprint->getDropFields())) {
            $entity->customFields()->whereIn('key', $blueprint->getDropFields())->delete();
        }
    }

    public static function update(string $slug, Closure $callback): void
    {
        $entity = Entity::where('slug', $slug)->firstOrFail();

        $blueprint = new Blueprint($entity->name);
        $callback($blueprint);

        // Update entity attributes (excluding name/slug unless changed)
        $attributesToUpdate = collect($blueprint->getEntityAttributes())
            ->except(['name', 'slug']) // Maybe we shouldn't update these by default if just updating fields
            ->filter()
            ->toArray();

        if (! empty($attributesToUpdate)) {
            $entity->update($attributesToUpdate);
        }

        $groupIds = static::syncFieldGroups($entity, $blueprint);

        // Process Fields
        $maxOrder = $entity->customFields()->max('order') ?? 0;

        foreach ($blueprint->getFields() as $fieldDefinition) {
            $fieldAttrs = static::resolveGroup($fieldDefinition->getAttributes(), $groupIds);

            $existingField = $entity->customFields()->where('key', $fieldAttrs['key'])->first();
            if (! $existingField) {
                $fieldAttrs['order'] = ++$maxOrder;
            }

            $entity->customFields()->updateOrCreate(
                ['key' => $fieldAttrs['key']],
                $fieldAttrs
            );
        }

        // Drop Fields if any
        if (! empty($blueprint->getDropFields())) {
            $entity->customFields()->whereIn('key', $blueprint->getDropFields())->delete();
        }
    }

    /**
     * Make an entity match the blueprint exactly: create or update the entity
     * and its fields, in the declared order. Fields the blueprint no longer
     * declares are deactivated, keeping their values so declaring them again
     * brings the content back, or deleted with their values when
     * $deleteMissing is true.
     */
    public static function sync(string $name, Closure $callback, bool $deleteMissing = false): Entity
    {
        $blueprint = new Blueprint($name);
        $callback($blueprint);

        $entityAttributes = $blueprint->getEntityAttributes();

        $entity = Entity::updateOrCreate(
            ['slug' => $entityAttributes['slug']],
            $entityAttributes
        );

        $groupIds = static::syncFieldGroups($entity, $blueprint);

        $order = 1;
        $declaredKeys = [];

        foreach ($blueprint->getFields() as $fieldDefinition) {
            $fieldAttrs = static::resolveGroup($fieldDefinition->getAttributes(), $groupIds);
            $fieldAttrs['order'] = $order++;
            $declaredKeys[] = $fieldAttrs['key'];

            $entity->customFields()->updateOrCreate(
                ['key' => $fieldAttrs['key']],
                $fieldAttrs
            );
        }

        // One by one, so the field cache of the entity is invalidated.
        $missingFields = $entity->customFields()->whereNotIn('key', $declaredKeys)->get();

        foreach ($missingFields as $missingField) {
            if (! $deleteMissing) {
                $missingField->update(['is_active' => false]);

                continue;
            }

            $missingField->values()->delete();
            $missingField->delete();
        }

        return $entity;
    }

    public static function drop(string $slug): void
    {
        $entity = Entity::where('slug', $slug)->first();
        if ($entity) {
            $entity->customFields()->delete();
            $entity->records()->delete(); // Depends on if you want cascading deletes
            $entity->delete();
        }
    }

    /**
     * Create or update the field groups declared in the blueprint.
     *
     * @return array<string, int> group key => id
     */
    protected static function syncFieldGroups(Entity $entity, Blueprint $blueprint): array
    {
        if (! FlexSchema::hasFieldGroups()) {
            return [];
        }

        $ids = $entity->fieldGroups()->pluck('id', 'key')->all();

        foreach ($blueprint->getFieldGroups() as $key => $group) {
            $model = $entity->fieldGroups()->updateOrCreate(
                ['key' => $key],
                array_merge($group, ['tenant_id' => $entity->tenant_id]),
            );

            $ids[$key] = $model->id;
        }

        return $ids;
    }

    /**
     * Replace the 'group' key of a field definition by its field_group_id.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, int>  $groupIds
     * @return array<string, mixed>
     */
    protected static function resolveGroup(array $attributes, array $groupIds): array
    {
        if (array_key_exists('group', $attributes)) {
            if (FlexSchema::hasFieldGroups()) {
                $attributes['field_group_id'] = $groupIds[$attributes['group']] ?? null;
            }

            unset($attributes['group']);
        }

        return $attributes;
    }
}
