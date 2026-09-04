<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Exception;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;

class FieldCache
{
    /**
     * Get active custom fields for an entity, utilizing cache if enabled.
     *
     * @return Collection<int, CustomField>
     */
    public static function getForEntity(Entity | string | int $entity): Collection
    {
        $entityModel = self::resolveEntity($entity);
        if (! $entityModel) {
            return new Collection;
        }

        if (! self::isEnabled()) {
            return $entityModel->customFields()->where('is_active', true)->orderBy('order')->get();
        }

        $key = self::makeCacheKey($entityModel);
        $ttl = (int) config('flex-fields.cache.ttl', 86400);
        $store = config('flex-fields.cache.store');

        $cache = $store ? Cache::store($store) : Cache::store();

        return $cache->remember($key, $ttl, function () use ($entityModel) {
            return $entityModel->customFields()->where('is_active', true)->orderBy('order')->get();
        });
    }

    /**
     * Forget cached active fields for a given entity or entity ID/slug.
     */
    public static function forgetForEntity(Entity | string | int | null $entity): bool
    {
        if (! $entity) {
            return false;
        }

        $entityModel = self::resolveEntity($entity);
        $store = config('flex-fields.cache.store');
        $cache = $store ? Cache::store($store) : Cache::store();

        if ($entityModel) {
            $key = self::makeCacheKey($entityModel);
            $cache->forget($key);
        }

        // Also forget using slug/id directly to cover all resolutions
        if (is_string($entity) || is_int($entity)) {
            $prefix = config('flex-fields.cache.prefix', 'flex_fields_');
            $cache->forget($prefix . 'active_fields_' . $entity);
        }

        return true;
    }

    /**
     * Flush all flex fields caches.
     */
    public static function flush(): bool
    {
        $store = config('flex-fields.cache.store');
        $cache = $store ? Cache::store($store) : Cache::store();

        if (method_exists($cache->getStore(), 'flush')) {
            return $cache->flush();
        }

        return false;
    }

    public static function isEnabled(): bool
    {
        return (bool) config('flex-fields.cache.enabled', true);
    }

    protected static function resolveEntity(Entity | string | int $entity): ?Entity
    {
        if ($entity instanceof Entity) {
            return $entity;
        }

        if (is_numeric($entity)) {
            return Entity::find($entity);
        }

        if (is_string($entity)) {
            return Entity::where('slug', $entity)->first();
        }

        return null;
    }

    protected static function makeCacheKey(Entity $entity): string
    {
        $prefix = config('flex-fields.cache.prefix', 'flex_fields_');
        $tenantKey = '';

        if (class_exists(Filament::class)) {
            try {
                if (Filament::hasTenancy()) {
                    $tenant = Filament::getTenant();
                    if ($tenant) {
                        $tenantKey = 't' . ($tenant->getKey() ?? '') . '_';
                    }
                }
            } catch (Exception $e) {
                // Ignore NoDefaultPanelSetException in console/API contexts
            }
        }

        return $prefix . 'active_fields_' . $tenantKey . $entity->id;
    }
}
