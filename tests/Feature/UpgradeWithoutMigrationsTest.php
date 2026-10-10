<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages\CreateEntityData;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages\EditEntityData;
use IvanMercedes\FlexFields\Schema\Blueprint;
use IvanMercedes\FlexFields\Schema\Flex;
use IvanMercedes\FlexFields\Services\EntityDomainService;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;
use IvanMercedes\FlexFields\Support\FlexSchema;
use IvanMercedes\FlexFields\Tests\FilamentTestCase;
use Livewire\Livewire;

/**
 * Simulates an application that updated the package but has not run the
 * 0.6.0 migrations yet (no field groups, no revisions table).
 */
class UpgradeWithoutMigrationsTest extends FilamentTestCase
{
    protected Entity $entity;

    protected function setUp(): void
    {
        FlexSchema::flush();

        parent::setUp();

        $this->entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->entity->customFields()->create(['label' => 'Price', 'key' => 'price', 'type' => 'number']);
        // Rules stored by older versions that are not in the whitelist must be ignored.
        $this->entity->customFields()->create(['label' => 'Code', 'key' => 'code', 'type' => 'text', 'validation_rules' => ['string', 'foo_rule', 'exists:users,id', 'max:10']]);
    }

    protected function tearDown(): void
    {
        FlexSchema::flush();

        parent::tearDown();
    }

    public function test_optional_schema_is_detected_as_missing(): void
    {
        $this->assertFalse(FlexSchema::hasFieldGroups());
        $this->assertFalse(FlexSchema::hasRevisions());
    }

    public function test_domain_operations_keep_working(): void
    {
        $records = new RecordDomainService;

        $record = $records->create('product', ['title' => 'Phone'], ['price' => 10, 'code' => 'ABC']);
        $records->update('product', $record->id, ['title' => 'Phone 2'], ['price' => 12]);
        $records->delete('product', $record->id);
        $records->restore('product', $record->id);
        $records->delete('product', $record->id, force: true);

        $field = (new FieldDomainService)->create('product', ['label' => 'Color', 'type' => 'text', 'group' => 'ignored']);
        $this->assertSame('color', $field->key);

        $schema = (new EntityDomainService)->getSchema('product');
        $this->assertSame([], $schema['groups']);
        $this->assertNull($schema['fields'][0]['group']);

        Flex::create('Article', function (Blueprint $table) {
            $table->fieldGroup('seo', 'SEO', fields: fn (Blueprint $t) => $t->text('meta_title'));
        });
        $this->assertSame(['meta_title'], Entity::where('slug', 'article')->first()->customFields->pluck('key')->all());

        $this->assertNotEmpty(DynamicFormBuilder::build($this->entity->fresh()));
    }

    public function test_filament_record_forms_keep_working(): void
    {
        Livewire::withQueryParams(['entity' => $this->entity->id])
            ->test(CreateEntityData::class)
            ->fillForm(['title' => 'Chair', 'status' => 'published', 'ff_price' => 5, 'ff_code' => 'X1'])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = $this->entity->records()->first();

        Livewire::test(EditEntityData::class, ['record' => $record->getKey()])
            ->assertOk()
            ->fillForm(['ff_code' => 'TOO-LONG-CODE'])
            ->call('save')
            ->assertHasFormErrors(['ff_code']); // max:10 (whitelisted) still applies
    }

    protected function packageMigrations(): array
    {
        return array_values(array_filter(
            parent::packageMigrations(),
            fn (string $file) => ! str_contains($file, '000009') && ! str_contains($file, '000010'),
        ));
    }
}
