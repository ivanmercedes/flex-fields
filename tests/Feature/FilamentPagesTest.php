<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages\CreateCustomField;
use IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages\EditCustomField;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages\CreateEntityData;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages\EditEntityData;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages\ImportEntityData;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages\ListEntityData;
use IvanMercedes\FlexFields\Resources\EntityDataResource\RelationManagers\RevisionsRelationManager;
use IvanMercedes\FlexFields\Resources\EntityResource\Pages\EditEntity;
use IvanMercedes\FlexFields\Resources\EntityResource\RelationManagers\FieldGroupsRelationManager;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\FieldGroupDomainService;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Tests\FilamentTestCase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

class FilamentPagesTest extends FilamentTestCase
{
    protected Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        config(['flex-fields.translations.locales' => ['en', 'es']]);

        Entity::create(['name' => 'Brand', 'slug' => 'brand']);
        $this->entity = Entity::create(['name' => 'Product', 'slug' => 'product']);

        $fields = new FieldDomainService;
        (new FieldGroupDomainService)->create('product', ['label' => 'Details']);
        $fields->create('product', ['label' => 'Kind', 'type' => 'select', 'options' => ['digital' => 'Digital', 'physical' => 'Physical']]);
        $fields->create('product', ['label' => 'Weight', 'type' => 'number', 'group' => 'details', 'settings' => ['show_when' => [['field' => 'kind', 'value' => 'physical']]]]);
        $fields->create('product', ['label' => 'Name', 'type' => 'text', 'settings' => ['translatable' => true]]);
        $fields->create('product', ['label' => 'Phone', 'type' => 'phone']);
        $fields->create('product', ['label' => 'Address', 'type' => 'address']);
        $fields->create('product', ['label' => 'Brand', 'type' => 'relationship', 'settings' => ['related_entity' => 'brand']]);
    }

    public function test_record_list_renders_with_export_and_import_actions(): void
    {
        (new RecordDomainService)->create('product', ['title' => 'Phone']);

        Livewire::withQueryParams(['entity' => $this->entity->id])
            ->test(ListEntityData::class)
            ->assertOk()
            ->assertSee('Phone')
            ->assertTableActionVisible('export_csv')
            ->assertTableActionVisible('import_records');
    }

    public function test_fields_shown_in_list_become_table_columns(): void
    {
        CustomField::where('key', 'kind')->update(['is_shown_in_list' => true]);
        CustomField::where('key', 'name')->update(['is_shown_in_list' => true]);
        (new RecordDomainService)->create('product', ['title' => 'Chair'], ['kind' => 'physical', 'name' => ['en' => 'Chair', 'es' => 'Silla']]);

        Livewire::withQueryParams(['entity' => $this->entity->id])
            ->test(ListEntityData::class)
            ->assertTableColumnExists('ff_kind')
            ->assertTableColumnExists('ff_name')
            ->assertSee('Physical');
    }

    public function test_repeater_sub_fields_use_their_declared_keys(): void
    {
        (new FieldDomainService)->create('product', ['label' => 'Specs', 'type' => 'repeater', 'settings' => ['schema' => [
            ['label' => 'Spec Name', 'key' => 'spec_name', 'type' => 'text'],
        ]]]);

        $record = (new RecordDomainService)->create('product', ['title' => 'Desk'], ['specs' => [['spec_name' => 'Oak']]]);

        $component = Livewire::test(EditEntityData::class, ['record' => $record->getKey()])->assertOk();
        $this->assertSame(['Oak'], array_column(array_values($component->get('data.ff_specs')), 'spec_name'));

        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame([['spec_name' => 'Oak']], array_values($record->fresh()->getValue('specs')));
    }

    public function test_export_actions_download_csv(): void
    {
        $records = new RecordDomainService;
        $phone = $records->create('product', ['title' => 'Phone']);
        $records->create('product', ['title' => 'Tablet']);

        Livewire::withQueryParams(['entity' => $this->entity->id])
            ->test(ListEntityData::class)
            ->callTableAction('export_csv')
            ->assertFileDownloaded();

        Livewire::withQueryParams(['entity' => $this->entity->id])
            ->test(ListEntityData::class)
            ->callTableBulkAction('export_selected', [$phone])
            ->assertFileDownloaded();
    }

    public function test_create_record_with_new_field_types(): void
    {
        $brand = (new RecordDomainService)->create('brand', ['title' => 'Apple']);

        Livewire::withQueryParams(['entity' => $this->entity->id])
            ->test(CreateEntityData::class)
            ->assertOk()
            ->fillForm([
                'title' => 'Chair',
                'status' => 'published',
                'ff_kind' => 'physical',
                'ff_weight' => 12,
                'ff_name' => ['en' => 'Chair', 'es' => 'Silla'],
                'ff_phone' => '809-555-1234',
                'ff_address' => ['street' => 'Main 1', 'city' => 'SD', 'country' => 'DO'],
                'ff_brand' => $brand->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = $this->entity->records()->where('title', 'Chair')->first();

        $this->assertSame(['en' => 'Chair', 'es' => 'Silla'], $record->getValue('name'));
        $this->assertSame('Main 1', $record->getValue('address')['street']);
        $this->assertSame($brand->id, $record->getValue('brand'));
        $this->assertEquals(12, $record->getValue('weight'));
        $this->assertSame('created', $record->revisions()->first()->event);
        $this->assertSame('Admin', $record->revisions()->first()->user_name);
    }

    public function test_edit_record_fills_values_and_records_history(): void
    {
        $record = (new RecordDomainService)->create('product', ['title' => 'Chair'], ['name' => ['en' => 'Chair'], 'kind' => 'digital']);

        Livewire::test(EditEntityData::class, ['record' => $record->getKey()])
            ->assertOk()
            ->assertFormSet(['ff_name.en' => 'Chair', 'ff_kind' => 'digital'])
            ->fillForm(['ff_name' => ['en' => 'Chair', 'es' => 'Silla']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Silla', $record->fresh()->getTranslation('name', 'es'));
        $this->assertSame('updated', $record->revisions()->first()->event);
    }

    public function test_revisions_relation_manager_lists_and_reverts(): void
    {
        $records = new RecordDomainService;
        $record = $records->create('product', ['title' => 'Original']);
        $records->update('product', $record->id, ['title' => 'Changed']);
        $first = $record->revisions()->get()->last();

        Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $record, 'pageClass' => EditEntityData::class])
            ->assertOk()
            ->assertCanSeeTableRecords($record->revisions()->get())
            ->callTableAction('revert', $first);

        $this->assertSame('Original', $record->fresh()->title);
    }

    public function test_import_page_previews_and_imports_csv(): void
    {
        $file = UploadedFile::fake()->createWithContent('import.csv', "title,kind\nImported,digital\nBad,unknown");

        $component = Livewire::withQueryParams(['entity' => $this->entity->id])
            ->test(ImportEntityData::class)
            ->assertOk()
            ->fillForm(['file' => $file, 'mode' => 'create'])
            ->call('previewImport')
            ->assertSee('Imported')
            ->assertSet('preview.summary.valid', 1)
            ->assertSet('preview.summary.invalid', 1);

        $storedPath = $component->get('storedPath');
        Storage::disk('local')->assertExists($storedPath);

        $component->call('confirmImport')
            ->assertSet('result.created', 1)
            ->assertSet('result.skipped', 1);

        $this->assertDatabaseHas('ff_entity_records', ['title' => 'Imported']);
        $this->assertDatabaseMissing('ff_entity_records', ['title' => 'Bad']);
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_import_page_locks_the_stored_file_path(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::withQueryParams(['entity' => $this->entity->id])
            ->test(ImportEntityData::class)
            ->set('storedPath', '../../.env');
    }

    public function test_custom_field_form_maps_visual_builders(): void
    {
        Livewire::test(CreateCustomField::class)
            ->assertOk()
            ->fillForm([
                'entity_id' => $this->entity->id,
                'type' => 'text',
                'label' => 'Sku Code',
                'key' => 'sku_code',
                'validation_builder' => [['rule' => 'min', 'value' => '3'], ['rule' => 'alpha_num', 'value' => null]],
                'show_when_match' => 'any',
                'show_when_conditions' => [['field' => 'kind', 'operator' => 'equals', 'value' => 'physical']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $field = CustomField::where('key', 'sku_code')->first();
        $this->assertSame(['min:3', 'alpha_num'], $field->validation_rules);
        $this->assertSame('any', $field->settings['show_when']['match']);

        Livewire::test(EditCustomField::class, ['record' => $field->getKey()])
            ->assertOk()
            ->assertFormSet(['show_when_match' => 'any'])
            ->fillForm(['show_when_conditions' => [], 'validation_builder' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $field->refresh();
        $this->assertSame([], $field->validation_rules);
        $this->assertArrayNotHasKey('show_when', $field->settings ?? []);
    }

    public function test_entity_edit_page_shows_field_groups_manager(): void
    {
        Livewire::test(EditEntity::class, ['record' => $this->entity->getKey()])->assertOk();

        Livewire::test(FieldGroupsRelationManager::class, ['ownerRecord' => $this->entity, 'pageClass' => EditEntity::class])
            ->assertOk()
            ->assertSee('Details')
            ->callTableAction('create', data: ['label' => 'Pricing Info'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('ff_field_groups', ['key' => 'pricing_info', 'entity_id' => $this->entity->id]);
    }
}
