<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\Tools\AssignFieldCategoriesTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\GetEntitySchemaTool;
use IvanMercedes\FlexFields\Ai\Tools\GetFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\ListFieldsTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateFieldTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityCategory;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class SchemaAndFieldToolsTest extends TestCase
{
    protected Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create([
            'name' => 'Article',
            'slug' => 'article',
            'description' => 'Blog articles',
            'is_active' => true,
        ]);
    }

    public function test_get_entity_schema_tool_returns_complete_introspection(): void
    {
        // Add multiple field types including repeater and select
        $this->entity->customFields()->create([
            'label' => 'Title',
            'key' => 'title_field',
            'type' => 'text',
            'is_required' => true,
            'is_shown_in_list' => true,
            'order' => 1,
        ]);

        $this->entity->customFields()->create([
            'label' => 'Category Choice',
            'key' => 'category_choice',
            'type' => 'select',
            'options' => ['news' => 'News', 'updates' => 'Product Updates'],
            'order' => 2,
        ]);

        $this->entity->customFields()->create([
            'label' => 'Gallery',
            'key' => 'gallery',
            'type' => 'repeater',
            'settings' => [
                'schema' => [
                    ['label' => 'Image URL', 'key' => 'url', 'type' => 'url'],
                    ['label' => 'Caption', 'key' => 'caption', 'type' => 'text'],
                ],
            ],
            'order' => 3,
        ]);

        $tool = new GetEntitySchemaTool;
        $response = $tool->handle(new Request(['entity' => 'article']));

        $data = json_decode($response, true);
        $this->assertEquals('article', $data['slug']);
        $this->assertEquals(3, $data['fields_count']);
        $this->assertCount(3, $data['fields']);

        // Check fields details
        $titleField = collect($data['fields'])->firstWhere('key', 'title_field');
        $this->assertNotNull($titleField);
        $this->assertTrue($titleField['is_required']);
        $this->assertEquals('text', $titleField['type']);

        $selectField = collect($data['fields'])->firstWhere('key', 'category_choice');
        $this->assertEquals(['news' => 'News', 'updates' => 'Product Updates'], $selectField['options']);

        $repeaterField = collect($data['fields'])->firstWhere('key', 'gallery');
        $this->assertEquals('repeater', $repeaterField['type']);
        $this->assertCount(2, $repeaterField['repeater_subfields']);
    }

    public function test_create_field_tool(): void
    {
        $tool = new CreateFieldTool;
        $response = $tool->handle(new Request([
            'entity' => 'article',
            'label' => 'Price USD',
            'key' => 'price_usd',
            'type' => 'number',
            'is_required' => true,
            'width' => 'half',
        ]));

        $data = json_decode($response, true);
        $this->assertArrayHasKey('field', $data);
        $this->assertEquals('price_usd', $data['field']['key']);
        $this->assertTrue($data['field']['is_required']);

        $this->assertDatabaseHas('ff_custom_fields', [
            'entity_id' => $this->entity->id,
            'key' => 'price_usd',
            'type' => 'number',
        ]);
    }

    public function test_list_and_get_field_tools(): void
    {
        $field = $this->entity->customFields()->create([
            'label' => 'Subtitle',
            'key' => 'subtitle',
            'type' => 'text',
        ]);

        $listTool = new ListFieldsTool;
        $listResponse = json_decode($listTool->handle(new Request(['entity' => 'article'])), true);
        $this->assertEquals(1, $listResponse['count']);
        $this->assertEquals('subtitle', $listResponse['fields'][0]['key']);

        $getTool = new GetFieldTool;
        $getResponse = json_decode($getTool->handle(new Request([
            'entity' => 'article',
            'field' => 'subtitle',
        ])), true);
        $this->assertEquals('subtitle', $getResponse['key']);
        $this->assertEquals('Subtitle', $getResponse['label']);
    }

    public function test_update_field_tool(): void
    {
        $this->entity->customFields()->create([
            'label' => 'Status Tag',
            'key' => 'status_tag',
            'type' => 'text',
            'is_required' => false,
        ]);

        $tool = new UpdateFieldTool;
        $response = $tool->handle(new Request([
            'entity' => 'article',
            'field' => 'status_tag',
            'label' => 'Publication Tag',
            'is_required' => true,
        ]));

        $data = json_decode($response, true);
        $this->assertEquals('Publication Tag', $data['field']['label']);
        $this->assertTrue($data['field']['is_required']);

        $this->assertDatabaseHas('ff_custom_fields', [
            'entity_id' => $this->entity->id,
            'key' => 'status_tag',
            'label' => 'Publication Tag',
            'is_required' => true,
        ]);
    }

    public function test_delete_field_tool_requires_approval_and_deletes(): void
    {
        $field = $this->entity->customFields()->create([
            'label' => 'To Drop',
            'key' => 'to_drop',
            'type' => 'text',
        ]);

        $tool = new DeleteFieldTool;
        $request = new Request(['entity' => 'article', 'field' => 'to_drop']);

        $approval = $tool->shouldRequestApproval($request);
        $this->assertNotNull($approval);
        $this->assertStringContainsString('to_drop', $approval->reason ?? '');

        $tool->withoutApproval();
        $result = $tool->handle($request);
        $this->assertStringContainsString('permanently deleted', $result);
        $this->assertDatabaseMissing('ff_custom_fields', ['id' => $field->id]);
    }

    public function test_create_and_update_field_with_categories(): void
    {
        $cat1 = EntityCategory::create([
            'entity_id' => $this->entity->id,
            'name' => 'News',
            'slug' => 'news',
        ]);

        $cat2 = EntityCategory::create([
            'entity_id' => $this->entity->id,
            'name' => 'Opinion',
            'slug' => 'opinion',
        ]);

        $createTool = new CreateFieldTool;
        $createResponse = json_decode($createTool->handle(new Request([
            'entity' => 'article',
            'label' => 'Source URL',
            'key' => 'source_url',
            'type' => 'url',
            'category_ids' => [$cat1->id],
        ])), true);

        $this->assertEquals([$cat1->id], $createResponse['field']['category_ids']);

        $updateTool = new UpdateFieldTool;
        $updateResponse = json_decode($updateTool->handle(new Request([
            'entity' => 'article',
            'field' => 'source_url',
            'category_ids' => [$cat1->id, $cat2->id],
        ])), true);

        $this->assertEquals([$cat1->id, $cat2->id], $updateResponse['field']['category_ids']);
    }

    public function test_assign_field_categories_tool(): void
    {
        $cat1 = EntityCategory::create([
            'entity_id' => $this->entity->id,
            'name' => 'Tech',
            'slug' => 'tech',
        ]);

        $field1 = $this->entity->customFields()->create([
            'label' => 'Tech Spec',
            'key' => 'tech_spec',
            'type' => 'text',
        ]);

        $field2 = $this->entity->customFields()->create([
            'label' => 'Version',
            'key' => 'version',
            'type' => 'text',
        ]);

        $tool = new AssignFieldCategoriesTool;

        // Assign multiple fields using category slug
        $response = json_decode($tool->handle(new Request([
            'entity' => 'article',
            'fields' => ['tech_spec', 'version'],
            'categories' => ['tech'],
        ])), true);

        $this->assertCount(2, $response['fields']);
        $this->assertEquals([$cat1->id], $response['fields'][0]['category_ids']);
        $this->assertEquals([$cat1->id], $response['fields'][1]['category_ids']);

        // Clear categories (pass empty array)
        $clearResponse = json_decode($tool->handle(new Request([
            'entity' => 'article',
            'fields' => ['tech_spec'],
            'categories' => [],
        ])), true);

        $this->assertEquals([], $clearResponse['fields'][0]['category_ids']);
        $this->assertNull($field1->fresh()->category_ids);
    }
}
