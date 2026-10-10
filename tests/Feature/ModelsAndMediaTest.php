<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Support\FlexModels;
use IvanMercedes\FlexFields\Support\Keys;
use IvanMercedes\FlexFields\Support\MediaLibrary;
use IvanMercedes\FlexFields\Tests\TestCase;
use stdClass;

class CustomRecord extends EntityRecord {}

class ModelsAndMediaTest extends TestCase
{
    public function test_record_model_can_be_swapped(): void
    {
        config(['flex-fields.models.record' => CustomRecord::class]);

        $entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $entity->records()->create(['title' => 'One']);

        $this->assertSame(CustomRecord::class, FlexModels::record());
        $this->assertSame(CustomRecord::class, EntityDataResource::getModel());
        $this->assertInstanceOf(CustomRecord::class, $entity->records()->first());
    }

    public function test_invalid_record_model_falls_back_to_default(): void
    {
        config(['flex-fields.models.record' => stdClass::class]);

        $this->assertSame(EntityRecord::class, FlexModels::record());
    }

    public function test_media_library_is_disabled_without_spatie(): void
    {
        config(['flex-fields.media.driver' => 'spatie']);

        $field = new CustomField(['type' => 'image', 'key' => 'cover']);

        $this->assertFalse(MediaLibrary::enabled());
        $this->assertFalse(MediaLibrary::handles($field));
        $this->assertSame('ff_cover', MediaLibrary::collection($field));
        $this->assertSame([], MediaLibrary::urls(new EntityRecord));
    }

    public function test_keys_are_generated_in_snake_case(): void
    {
        $this->assertSame('canonical_url', Keys::make('Canonical URL'));
        $this->assertSame('cover_image', Keys::make('coverImage'));
        $this->assertSame('seo', Keys::make('SEO'));
        $this->assertSame('precio_en_dolares', Keys::make('Precio en dólares'));

        $entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->assertSame('main_image_url', $entity->customFields()->create(['label' => 'Main Image URL', 'type' => 'url'])->key);
    }
}
