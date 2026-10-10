<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Schema\Blueprint;
use IvanMercedes\FlexFields\Schema\Flex;
use IvanMercedes\FlexFields\Services\EntityDomainService;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;
use IvanMercedes\FlexFields\Tests\TestCase;
use RuntimeException;

class AdvancedFieldTypesTest extends TestCase
{
    protected Entity $products;

    protected Entity $brands;

    protected FieldDomainService $fields;

    protected RecordDomainService $records;

    protected function setUp(): void
    {
        parent::setUp();

        config(['flex-fields.translations.locales' => ['en', 'es']]);

        $this->brands = Entity::create(['name' => 'Brand', 'slug' => 'brand']);
        $this->products = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->fields = new FieldDomainService;
        $this->records = new RecordDomainService;
    }

    public function test_relationship_field_links_records_of_another_entity(): void
    {
        $field = $this->fields->create('product', [
            'label' => 'Brand', 'type' => 'relationship', 'settings' => ['related_entity' => (string) $this->brands->id],
        ]);
        $this->fields->create('product', [
            'label' => 'Related Brands', 'type' => 'relationship', 'settings' => ['related_entity' => 'brand', 'multiple' => true],
        ]);

        $this->assertSame('brand', $field->settings['related_entity']);
        $this->assertFalse($field->settings['multiple']);

        $apple = $this->records->create('brand', ['title' => 'Apple']);
        $samsung = $this->records->create('brand', ['title' => 'Samsung']);

        $phone = $this->records->create('product', ['title' => 'iPhone'], [
            'brand' => (string) $apple->id,
            'related_brands' => [$apple->id, $samsung->id],
        ]);

        $this->assertSame($apple->id, $phone->getValue('brand'));
        $this->assertSame([$apple->id, $samsung->id], $phone->getValue('related_brands'));
        $this->assertSame(['Apple'], $phone->related('brand')->pluck('title')->all());
        $this->assertCount(2, $phone->related('related_brands'));

        $schema = collect((new EntityDomainService)->getSchema('product')['fields'])->keyBy('key');
        $this->assertSame(['entity' => 'brand', 'multiple' => false], $schema['brand']['relationship']);
        $this->assertStringContainsString('record ID', $schema['brand']['value_format']);
    }

    public function test_relationship_rejects_records_from_other_entities(): void
    {
        $this->fields->create('product', ['label' => 'Brand', 'type' => 'relationship', 'settings' => ['related_entity' => 'brand']]);
        $otherProduct = $this->records->create('product', ['title' => 'Not a brand']);

        $this->expectException(ValidationException::class);
        $this->records->create('product', ['title' => 'Phone'], ['brand' => $otherProduct->id]);
    }

    public function test_relationship_requires_existing_related_entity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->fields->create('product', ['label' => 'Brand', 'type' => 'relationship', 'settings' => ['related_entity' => 'nope']]);
    }

    public function test_relationship_does_not_cross_tenants(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);

        $tenantBrands = Entity::create(['name' => 'Brand', 'slug' => 'brand', 'tenant_id' => 2]);
        $tenantProducts = Entity::create(['name' => 'Product', 'slug' => 'product', 'tenant_id' => 1]);

        $this->expectException(RuntimeException::class);
        $this->fields->create($tenantProducts->id, [
            'label' => 'Brand', 'type' => 'relationship', 'settings' => ['related_entity' => $tenantBrands->slug],
        ], 1);
    }

    public function test_phone_and_address_values_are_validated_and_normalized(): void
    {
        $this->fields->create('product', ['label' => 'Phone', 'type' => 'phone']);
        $this->fields->create('product', ['label' => 'Address', 'type' => 'address', 'settings' => ['address_parts' => ['street', 'city', 'bogus']]]);

        $record = $this->records->create('product', ['title' => 'Store'], [
            'phone' => '+1 (809) 555-1234',
            'address' => ['street' => 'Main St 1', 'city' => 'Santo Domingo', 'country' => 'ignored'],
        ]);

        $this->assertSame('+1 (809) 555-1234', $record->getValue('phone'));
        $this->assertSame(['street' => 'Main St 1', 'city' => 'Santo Domingo'], $record->getValue('address'));
        $this->assertSame(['street', 'city'], CustomField::where('key', 'address')->first()->settings['address_parts']);

        $this->expectException(ValidationException::class);
        $this->records->setValues('product', $record->id, ['phone' => 'call me maybe']);
    }

    public function test_translatable_fields_store_values_per_locale(): void
    {
        $this->fields->create('product', ['label' => 'Name', 'type' => 'text', 'is_required' => true, 'settings' => ['translatable' => true]]);
        $this->fields->create('product', ['label' => 'Sku', 'type' => 'number', 'settings' => ['translatable' => true]]);

        $this->assertFalse(CustomField::where('key', 'sku')->first()->settings['translatable']);

        $record = $this->records->create('product', ['title' => 'Chair'], ['name' => ['en' => 'Chair', 'es' => 'Silla']]);

        $this->assertSame(['en' => 'Chair', 'es' => 'Silla'], $record->getValue('name'));
        $this->assertSame('Silla', $record->getTranslation('name', 'es'));
        $this->assertSame('Chair', $record->getTranslation('name', 'fr'));
        $this->assertNull($record->getTranslation('name', 'fr', useFallback: false));

        $schema = collect((new EntityDomainService)->getSchema('product')['fields'])->keyBy('key');
        $this->assertTrue($schema['name']['translatable']);
        $this->assertStringContainsString('locale', $schema['name']['value_format']);

        try {
            $this->records->create('product', ['title' => 'Table'], ['name' => ['es' => 'Mesa']]);
            $this->fail('The default locale is required for required translatable fields.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name.en', $e->errors());
        }
    }

    public function test_legacy_plain_values_of_translatable_fields_map_to_fallback_locale(): void
    {
        $field = $this->products->customFields()->create(['label' => 'Title', 'key' => 'title_field', 'type' => 'text']);
        $record = $this->records->create('product', ['title' => 'Legacy'], ['title_field' => 'Plain']);

        $field->update(['settings' => ['translatable' => true]]);

        $this->assertSame(['en' => 'Plain'], $record->fresh()->getValue('title_field'));
    }

    public function test_show_when_settings_are_validated(): void
    {
        $this->fields->create('product', ['label' => 'Type', 'type' => 'select', 'options' => ['digital', 'physical']]);

        $weight = $this->fields->create('product', [
            'label' => 'Weight', 'type' => 'number',
            'settings' => ['show_when' => [['field' => 'type', 'operator' => 'equals', 'value' => 'physical']]],
        ]);

        $this->assertSame(['match' => 'all', 'conditions' => [['field' => 'type', 'operator' => 'equals', 'value' => 'physical']]], $weight->settings['show_when']);
        $this->assertSame([['value' => 'digital', 'label' => 'digital'], ['value' => 'physical', 'label' => 'physical']], CustomField::where('key', 'type')->first()->options);

        try {
            $this->fields->update('product', 'weight', ['settings' => ['show_when' => [['field' => 'weight']]]]);
            $this->fail('Self reference must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('own value', $e->getMessage());
        }

        try {
            $this->fields->update('product', 'weight', ['settings' => ['show_when' => [['field' => 'missing']]]]);
            $this->fail('Unknown field must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('unknown field', $e->getMessage());
        }

        // Partial settings updates are merged, null removes the key.
        $updated = $this->fields->update('product', 'weight', ['settings' => ['show_when' => null, 'unit' => 'kg']]);
        $this->assertArrayNotHasKey('show_when', $updated->settings);
        $this->assertSame('kg', $updated->settings['unit']);
    }

    public function test_validation_rules_are_sanitized_and_enforced(): void
    {
        $this->fields->create('product', ['label' => 'Code', 'type' => 'text', 'validation_rules' => ['required', 'min:3', 'alpha_num']]);
        $this->assertSame(['min:3', 'alpha_num'], CustomField::where('key', 'code')->first()->validation_rules);

        try {
            $this->fields->create('product', ['label' => 'Bad', 'type' => 'text', 'validation_rules' => ['exists:users,id']]);
            $this->fail('Unknown rules must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not allowed', $e->getMessage());
        }

        $this->assertSame('ABC1', $this->records->create('product', ['title' => 'ok'], ['code' => 'ABC1'])->getValue('code'));

        $this->expectException(ValidationException::class);
        $this->records->create('product', ['title' => 'bad'], ['code' => 'a!']);
    }

    public function test_select_and_required_fields_are_validated(): void
    {
        $this->fields->create('product', ['label' => 'Size', 'type' => 'select', 'options' => ['s' => 'Small'], 'is_required' => true]);

        try {
            $this->records->create('product', ['title' => 'No size']);
            $this->fail('Required field missing.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('size', $e->errors());
        }

        $record = $this->records->create('product', ['title' => 'Ok'], ['size' => 's']);

        // Partial updates do not require other fields.
        $this->records->update('product', $record->id, ['title' => 'Renamed']);

        $this->expectException(ValidationException::class);
        $this->records->setValues('product', $record->id, ['size' => 'xl']);
    }

    public function test_legacy_prefixed_repeater_items_are_normalized(): void
    {
        $this->products->customFields()->create(['label' => 'Links', 'key' => 'links', 'type' => 'repeater', 'settings' => ['schema' => [['label' => 'Url', 'key' => 'url', 'type' => 'text']]]]);
        $record = $this->products->records()->create(['title' => 'Legacy']);
        $record->setValue('links', [['ff_url' => 'https://a.test'], ['url' => 'https://b.test']]);

        $this->assertSame([['url' => 'https://a.test'], ['url' => 'https://b.test']], $record->fresh()->getValue('links'));
    }

    public function test_form_builder_creates_components_for_new_types(): void
    {
        $this->fields->create('product', ['label' => 'Type', 'type' => 'select', 'options' => ['digital' => 'Digital']]);
        $this->fields->create('product', ['label' => 'Phone', 'type' => 'phone', 'settings' => ['phone_mask' => '(999) 999-9999']]);
        $this->fields->create('product', ['label' => 'Address', 'type' => 'address']);
        $this->fields->create('product', ['label' => 'Brand', 'type' => 'relationship', 'settings' => ['related_entity' => 'brand']]);
        $this->fields->create('product', ['label' => 'Name', 'type' => 'text', 'settings' => ['translatable' => true]]);
        $this->fields->create('product', [
            'label' => 'Download', 'type' => 'url', 'validation_rules' => ['starts_with:https'],
            'settings' => ['show_when' => [['field' => 'type', 'value' => 'digital']]],
        ]);
        $this->records->create('brand', ['title' => 'Apple']);

        $fields = $this->products->fresh()->customFields->keyBy('key');
        $components = collect(DynamicFormBuilder::buildFieldComponents($fields->all()))->keyBy(fn ($c) => method_exists($c, 'getName') ? $c->getName() : $c->getLabel());

        $this->assertInstanceOf(TextInput::class, $components['ff_phone']);
        $this->assertTrue($components['ff_phone']->isTel());
        $this->assertInstanceOf(Fieldset::class, $components['Address']);
        $this->assertSame('ff_address', $components['Address']->getStatePath(false));
        $this->assertInstanceOf(Select::class, $components['ff_brand']);
        $this->assertSame(['Apple'], array_values(DynamicFormBuilder::relationshipOptions($fields['brand'])));
        $this->assertInstanceOf(Tabs::class, $components['Name']);
        $this->assertTrue($components['ff_type']->isLive());
        $this->assertContains('starts_with:https', $components['ff_download']->getValidationRules());
        $this->assertSame(['type'], DynamicFormBuilder::controllingKeys($fields->all()));
    }

    public function test_schema_builder_supports_new_types_and_modifiers(): void
    {
        Flex::create('Store', function (Blueprint $table) {
            $table->select('kind')->options(['online' => 'Online', 'physical' => 'Physical']);
            $table->address('location')->addressParts(['street', 'city'])->showWhen('kind', 'equals', 'physical');
            $table->phone('phone')->phoneMask('999-999-9999');
            $table->relationship('brand', 'brand', multiple: true);
            $table->text('tagline')->translatable();
            $table->url('website')->showWhen('kind', 'equals', 'online')->showWhen('phone', 'filled')->showWhenAny();
        });

        $fields = Entity::where('slug', 'store')->first()->customFields->keyBy('key');

        $this->assertSame('physical', $fields['location']->settings['show_when']['conditions'][0]['value']);
        $this->assertSame(['street', 'city'], $fields['location']->settings['address_parts']);
        $this->assertSame('999-999-9999', $fields['phone']->settings['phone_mask']);
        $this->assertSame(['related_entity' => 'brand', 'multiple' => true], $fields['brand']->settings);
        $this->assertTrue($fields['tagline']->isTranslatable());
        $this->assertSame('any', $fields['website']->settings['show_when']['match']);
        $this->assertCount(2, $fields['website']->settings['show_when']['conditions']);
    }
}
