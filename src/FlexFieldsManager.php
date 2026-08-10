<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Schema\Flex;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;
use IvanMercedes\FlexFields\Support\EntityQuery;
use IvanMercedes\FlexFields\Support\FieldCache;

class FlexFieldsManager
{
    /**
     * Get a fluent query wrapper for a given entity (by slug, ID, or model).
     */
    public function entity(Entity | string | int $entity): EntityQuery
    {
        return new EntityQuery($entity);
    }

    /**
     * Get Eloquent query builder for all entities.
     *
     * @return Builder<Entity>
     */
    public function entities(): Builder
    {
        return Entity::query();
    }

    /**
     * Define a new entity via code-first Schema Builder.
     */
    public function makeEntity(string $name, Closure $callback): void
    {
        Flex::create($name, $callback);
    }

    /**
     * Update an entity via code-first Schema Builder.
     */
    public function updateEntity(string $slug, Closure $callback): void
    {
        Flex::update($slug, $callback);
    }

    /**
     * Drop an entity via code-first Schema Builder.
     */
    public function dropEntity(string $slug): void
    {
        Flex::drop($slug);
    }

    /**
     * Build Filament form schema for an entity.
     */
    public function form(Entity | string | int $entity): array
    {
        $entityModel = $entity instanceof Entity ? $entity : Entity::where('slug', $entity)->first();

        if (! $entityModel) {
            return [];
        }

        return DynamicFormBuilder::build($entityModel);
    }

    /**
     * Clear field cache for a specific entity or all entities.
     */
    public function clearCache(?string $entitySlug = null): bool
    {
        if ($entitySlug !== null) {
            return FieldCache::forgetForEntity($entitySlug);
        }

        return FieldCache::flush();
    }

    /**
     * Get status summary statistics for FlexFields.
     */
    public function status(): array
    {
        $entitiesTotal = Entity::count();
        $entitiesActive = Entity::where('is_active', true)->count();

        $fieldsTotal = CustomField::count();
        $fieldsActive = CustomField::where('is_active', true)->count();

        $recordsTotal = EntityRecord::count();
        $recordsPublished = EntityRecord::where('status', 'published')->count();
        $recordsDraft = EntityRecord::where('status', 'draft')->count();

        $trashedRecords = EntityRecord::onlyTrashed()->count();

        $tenancyEnabled = (bool) config('flex-fields.tenancy.enabled', false);
        $cacheEnabled = (bool) config('flex-fields.cache.enabled', true);

        return [
            'entities' => [
                'total' => $entitiesTotal,
                'active' => $entitiesActive,
                'inactive' => $entitiesTotal - $entitiesActive,
            ],
            'fields' => [
                'total' => $fieldsTotal,
                'active' => $fieldsActive,
                'inactive' => $fieldsTotal - $fieldsActive,
            ],
            'records' => [
                'total' => $recordsTotal,
                'published' => $recordsPublished,
                'draft' => $recordsDraft,
                'trashed' => $trashedRecords,
            ],
            'system' => [
                'tenancy_enabled' => $tenancyEnabled,
                'cache_enabled' => $cacheEnabled,
                'uploads_disk' => config('flex-fields.uploads.disk', 'public'),
                'uploads_visibility' => config('flex-fields.uploads.visibility', 'public'),
            ],
        ];
    }
}
