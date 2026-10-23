<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Support\FieldCache;
use IvanMercedes\FlexFields\Tests\TestCase;

class FieldCacheTest extends TestCase
{
    public function test_cached_fields_work_with_stores_that_do_not_unserialize_classes(): void
    {
        // The default of new Laravel applications: no PHP classes are unserialized from the cache.
        config([
            'cache.default' => 'array',
            'cache.stores.array.serialize' => true,
            'cache.serializable_classes' => false,
            'flex-fields.cache.enabled' => true,
        ]);
        Cache::forgetDriver('array');

        $entity = Entity::create(['name' => 'Page', 'slug' => 'page']);
        $entity->customFields()->create(['label' => 'Title', 'key' => 'title', 'type' => 'text', 'order' => 2]);
        $entity->customFields()->create(['label' => 'Cta', 'key' => 'cta', 'type' => 'link', 'order' => 1, 'settings' => ['foo' => 'bar']]);
        $entity->customFields()->create(['label' => 'Old', 'key' => 'old', 'type' => 'text', 'is_active' => false]);

        FieldCache::getForEntity($entity);
        $fields = FieldCache::getForEntity($entity);

        $this->assertInstanceOf(Collection::class, $fields);
        $this->assertContainsOnlyInstancesOf(CustomField::class, $fields);
        $this->assertSame(['cta', 'title'], $fields->pluck('key')->all());
        $this->assertSame(['foo' => 'bar'], $fields->first()->settings);
        $this->assertTrue($fields->first()->exists);
    }

    public function test_fields_cached_as_models_by_older_versions_are_rebuilt(): void
    {
        config(['flex-fields.cache.enabled' => true]);

        $entity = Entity::create(['name' => 'Page', 'slug' => 'page']);
        $entity->customFields()->create(['label' => 'Title', 'key' => 'title', 'type' => 'text']);
        Cache::put('flex_fields_active_fields_' . $entity->id, unserialize(serialize(new Collection), ['allowed_classes' => false]));

        $this->assertSame(['title'], FieldCache::getForEntity($entity)->pluck('key')->all());
    }
}
