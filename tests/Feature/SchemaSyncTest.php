<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Closure;
use IvanMercedes\FlexFields\Facades\FlexFields;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Schema\Blueprint;
use IvanMercedes\FlexFields\Schema\Flex;
use IvanMercedes\FlexFields\Tests\TestCase;

class SchemaSyncTest extends TestCase
{
    public function test_sync_creates_the_entity_and_its_fields_in_order(): void
    {
        $entity = Flex::sync('Home', function (Blueprint $table) {
            $table->slug('page-home');
            $table->text('hero_title');
            $table->link('hero_cta');
        });

        $this->assertSame('page-home', $entity->slug);
        $this->assertSame(['hero_title', 'hero_cta'], $entity->customFields()->orderBy('order')->pluck('key')->all());
    }

    public function test_sync_updates_the_entity_and_matches_the_declared_fields(): void
    {
        $this->syncHome(function (Blueprint $table) {
            $table->text('hero_title');
            $table->textarea('hero_text');
        });

        $entity = $this->syncHome(function (Blueprint $table) {
            $table->description('Landing page');
            $table->rich('hero_text', 'Intro');
            $table->text('hero_title');
        });

        $this->assertSame('Landing page', $entity->description);
        $this->assertSame(1, Entity::where('slug', 'page-home')->count());

        $fields = $entity->customFields()->orderBy('order')->get();
        $this->assertSame(['hero_text', 'hero_title'], $fields->pluck('key')->all());
        $this->assertSame('richtext', $fields->first()->type);
        $this->assertSame('Intro', $fields->first()->label);
    }

    public function test_fields_no_longer_declared_are_deactivated_and_keep_their_values(): void
    {
        $entity = $this->syncHome(function (Blueprint $table) {
            $table->text('hero_title');
            $table->text('hero_badge');
        });
        $record = $entity->records()->create(['title' => 'Home']);
        $record->setValue('hero_badge', 'New');

        $this->syncHome(fn (Blueprint $table) => $table->text('hero_title'));

        $this->assertSame(['hero_title'], $entity->fresh()->active_fields->pluck('key')->all());
        $this->assertFalse(CustomField::where('key', 'hero_badge')->first()->is_active);

        $this->syncHome(function (Blueprint $table) {
            $table->text('hero_title');
            $table->text('hero_badge');
        });

        $this->assertSame(['hero_title', 'hero_badge'], $entity->fresh()->active_fields->pluck('key')->all());
        $this->assertSame('New', $record->fresh()->getValue('hero_badge'));
    }

    public function test_fields_no_longer_declared_can_be_deleted_with_their_values(): void
    {
        $entity = $this->syncHome(function (Blueprint $table) {
            $table->text('hero_title');
            $table->text('hero_badge');
        });
        $entity->records()->create(['title' => 'Home'])->setValue('hero_badge', 'New');

        FlexFields::syncEntity('Home', function (Blueprint $table) {
            $table->slug('page-home');
            $table->text('hero_title');
        }, deleteMissing: true);

        $this->assertFalse(CustomField::where('key', 'hero_badge')->exists());
        $this->assertSame(0, $entity->records()->first()->fieldValues()->count());
    }

    protected function syncHome(Closure $fields): Entity
    {
        return Flex::sync('Home', function (Blueprint $table) use ($fields) {
            $table->slug('page-home');
            $fields($table);
        });
    }
}
