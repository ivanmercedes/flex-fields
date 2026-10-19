<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Filament\Schemas\Components\Section;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Schema\Blueprint;
use IvanMercedes\FlexFields\Schema\Flex;
use IvanMercedes\FlexFields\Services\EntityDomainService;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\FieldGroupDomainService;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;
use IvanMercedes\FlexFields\Tests\TestCase;
use RuntimeException;

class FieldGroupsTest extends TestCase
{
    protected Entity $entity;

    protected FieldGroupDomainService $groups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->entity->customFields()->create(['label' => 'Name', 'key' => 'name', 'type' => 'text']);
        $this->entity->customFields()->create(['label' => 'Meta Title', 'key' => 'meta_title', 'type' => 'text']);
        $this->entity->customFields()->create(['label' => 'Meta Description', 'key' => 'meta_description', 'type' => 'textarea']);

        $this->groups = new FieldGroupDomainService;
    }

    public function test_create_group_with_fields_and_unique_keys(): void
    {
        $group = $this->groups->create('product', [
            'label' => 'SEO',
            'description' => 'Search engine settings',
            'is_collapsed' => true,
            'fields' => ['meta_title', 'meta_description'],
        ]);

        $this->assertSame('seo', $group->key);
        $this->assertTrue($group->is_collapsible);
        $this->assertTrue($group->is_collapsed);
        $this->assertEqualsCanonicalizing(['meta_title', 'meta_description'], $group->fields->pluck('key')->all());

        $second = $this->groups->create('product', ['label' => 'SEO']);
        $this->assertSame('seo_1', $second->key);
        $this->assertSame(2, $second->order);
    }

    public function test_update_and_reject_duplicate_key(): void
    {
        $this->groups->create('product', ['label' => 'Pricing']);
        $seo = $this->groups->create('product', ['label' => 'SEO']);

        $updated = $this->groups->update('product', 'seo', ['label' => 'Search', 'is_collapsible' => false, 'fields' => ['name']]);
        $this->assertSame('Search', $updated->label);
        $this->assertFalse($updated->is_collapsible);
        $this->assertSame(['name'], $updated->fields->pluck('key')->all());

        $this->expectException(RuntimeException::class);
        $this->groups->update('product', $seo->id, ['key' => 'pricing']);
    }

    public function test_delete_group_keeps_fields_ungrouped(): void
    {
        $this->groups->create('product', ['label' => 'SEO', 'fields' => ['meta_title']]);

        $this->assertTrue($this->groups->delete('product', 'seo'));
        $this->assertDatabaseMissing('ff_field_groups', ['key' => 'seo']);
        $this->assertDatabaseHas('ff_custom_fields', ['key' => 'meta_title', 'field_group_id' => null]);
    }

    public function test_field_service_resolves_group_by_key(): void
    {
        $this->groups->create('product', ['label' => 'SEO']);
        $fields = new FieldDomainService;

        $field = $fields->create('product', ['label' => 'Canonical URL', 'type' => 'url', 'group' => 'seo']);
        $this->assertSame('seo', $field->fieldGroup->key);

        $moved = $fields->update('product', 'canonical_url', ['group' => '']);
        $this->assertNull($moved->field_group_id);

        $this->expectException(RuntimeException::class);
        $fields->create('product', ['label' => 'Other', 'type' => 'text', 'group' => 'missing']);
    }

    public function test_group_cannot_be_used_across_entities(): void
    {
        $other = Entity::create(['name' => 'Post', 'slug' => 'post']);
        $foreignGroup = $this->groups->create('post', ['label' => 'Foreign']);

        $this->expectException(RuntimeException::class);
        (new FieldDomainService)->update('product', 'name', ['group' => (string) $foreignGroup->id]);
    }

    public function test_schema_introspection_includes_groups(): void
    {
        $this->groups->create('product', ['label' => 'SEO', 'fields' => ['meta_title']]);

        $schema = (new EntityDomainService)->getSchema('product');

        $this->assertSame('seo', $schema['groups'][0]['key']);
        $metaTitle = collect($schema['fields'])->firstWhere('key', 'meta_title');
        $this->assertSame('seo', $metaTitle['group']);
        $this->assertNull(collect($schema['fields'])->firstWhere('key', 'name')['group']);
    }

    public function test_form_builder_renders_one_section_per_group(): void
    {
        $this->groups->create('product', ['label' => 'SEO', 'is_collapsed' => true, 'fields' => ['meta_title', 'meta_description']]);
        $this->groups->create('product', ['label' => 'Empty group']);

        $sections = DynamicFormBuilder::build($this->entity->fresh());

        $this->assertCount(2, $sections);
        $this->assertContainsOnlyInstancesOf(Section::class, $sections);
        $this->assertSame('SEO', $sections[1]->getHeading());
        $this->assertTrue($sections[1]->isCollapsible());
        $this->assertTrue($sections[1]->isCollapsed());
    }

    public function test_section_categories_come_from_its_fields(): void
    {
        $fields = $this->entity->customFields()->get()->keyBy('key');
        $fields['meta_title']->category_ids = [1, 2];
        $fields['meta_description']->category_ids = [2, 3];

        $this->assertSame([1, 2, 3], DynamicFormBuilder::sectionCategoryIds([$fields['meta_title'], $fields['meta_description']]));

        // A field shown in every category keeps the section always visible.
        $this->assertSame([], DynamicFormBuilder::sectionCategoryIds([$fields['meta_title'], $fields['name']]));
    }

    public function test_schema_builder_declares_groups(): void
    {
        Flex::create('Article', function (Blueprint $table) {
            $table->text('headline');
            $table->fieldGroup('seo', 'SEO', 'Search settings', collapsed: true, fields: function (Blueprint $table) {
                $table->text('meta_title');
            });
            $table->textarea('summary')->group('seo');
        });

        $article = Entity::where('slug', 'article')->first();
        $group = $article->fieldGroups()->first();

        $this->assertSame('SEO', $group->label);
        $this->assertTrue($group->is_collapsed);
        $this->assertEqualsCanonicalizing(['meta_title', 'summary'], $group->fields->pluck('key')->all());
        $this->assertNull($article->customFields()->where('key', 'headline')->first()->field_group_id);
    }

    public function test_deleting_entity_removes_groups(): void
    {
        $this->groups->create('product', ['label' => 'SEO']);

        (new EntityDomainService)->delete('product');

        $this->assertDatabaseCount('ff_field_groups', 0);
    }
}
