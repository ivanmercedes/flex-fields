<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Schema;

use Closure;
use IvanMercedes\FlexFields\Models\Entity;

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

        // Process Fields
        $order = 1;
        foreach ($blueprint->getFields() as $fieldDefinition) {
            $fieldAttrs = $fieldDefinition->getAttributes();
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

        // Process Fields
        $maxOrder = $entity->customFields()->max('order') ?? 0;

        foreach ($blueprint->getFields() as $fieldDefinition) {
            $fieldAttrs = $fieldDefinition->getAttributes();

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

    public static function drop(string $slug): void
    {
        $entity = Entity::where('slug', $slug)->first();
        if ($entity) {
            $entity->customFields()->delete();
            $entity->records()->delete(); // Depends on if you want cascading deletes
            $entity->delete();
        }
    }
}
