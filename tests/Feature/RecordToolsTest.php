<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\Tools\CreateRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\GetRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\ListRecordsTool;
use IvanMercedes\FlexFields\Ai\Tools\PublishRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\SetRecordValuesTool;
use IvanMercedes\FlexFields\Ai\Tools\UnpublishRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateRecordTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class RecordToolsTest extends TestCase
{
    protected Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create([
            'name' => 'Product',
            'slug' => 'product',
        ]);

        $this->entity->customFields()->create([
            'label' => 'Price',
            'key' => 'price',
            'type' => 'number',
        ]);

        $this->entity->customFields()->create([
            'label' => 'Color',
            'key' => 'color',
            'type' => 'text',
        ]);

        $this->entity->customFields()->create([
            'label' => 'Tags',
            'key' => 'tags',
            'type' => 'tags',
        ]);
    }

    public function test_create_record_tool_with_field_values_and_categories(): void
    {
        $category = $this->entity->categories()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $tool = new CreateRecordTool;
        $response = $tool->handle(new Request([
            'entity' => 'product',
            'title' => 'iPhone 16 Pro',
            'status' => 'published',
            'field_values' => [
                'price' => 999.99,
                'color' => 'Space Black',
                'tags' => ['smartphone', 'apple', 'flagship'],
            ],
            'category_ids' => [$category->id],
        ]));

        $data = json_decode($response, true);
        $this->assertArrayHasKey('record', $data);
        $record = $data['record'];
        $this->assertEquals('iPhone 16 Pro', $record['title']);
        $this->assertEquals('published', $record['status']);
        $this->assertEquals(999.99, $record['data']['price']);
        $this->assertEquals('Space Black', $record['data']['color']);
        $this->assertEquals(['smartphone', 'apple', 'flagship'], $record['data']['tags']);
        $this->assertEquals('Electronics', $record['categories'][0]['name']);
    }

    public function test_list_records_tool_with_filters(): void
    {
        $createTool = new CreateRecordTool;
        $createTool->handle(new Request([
            'entity' => 'product',
            'title' => 'Published Item',
            'status' => 'published',
        ]));
        $createTool->handle(new Request([
            'entity' => 'product',
            'title' => 'Draft Item',
            'status' => 'draft',
        ]));

        $listTool = new ListRecordsTool;

        // Filter published
        $publishedResponse = json_decode($listTool->handle(new Request([
            'entity' => 'product',
            'status' => 'published',
        ])), true);
        $this->assertEquals(1, $publishedResponse['count']);
        $this->assertEquals('Published Item', $publishedResponse['records'][0]['title']);

        // Filter draft
        $draftResponse = json_decode($listTool->handle(new Request([
            'entity' => 'product',
            'status' => 'draft',
        ])), true);
        $this->assertEquals(1, $draftResponse['count']);
        $this->assertEquals('Draft Item', $draftResponse['records'][0]['title']);
    }

    public function test_get_record_tool(): void
    {
        $createTool = new CreateRecordTool;
        $created = json_decode($createTool->handle(new Request([
            'entity' => 'product',
            'title' => 'Test Record',
            'field_values' => ['price' => 45.5],
        ])), true);

        $getTool = new GetRecordTool;
        $response = json_decode($getTool->handle(new Request([
            'entity' => 'product',
            'record' => (string) $created['record']['id'],
        ])), true);

        $this->assertEquals('Test Record', $response['title']);
        $this->assertEquals(45.5, $response['data']['price']);
    }

    public function test_update_record_tool(): void
    {
        $createTool = new CreateRecordTool;
        $created = json_decode($createTool->handle(new Request([
            'entity' => 'product',
            'title' => 'Before Update',
            'field_values' => ['price' => 10.0],
        ])), true);

        $updateTool = new UpdateRecordTool;
        $response = json_decode($updateTool->handle(new Request([
            'entity' => 'product',
            'record' => (string) $created['record']['id'],
            'title' => 'After Update',
            'field_values' => ['price' => 20.0],
        ])), true);

        $this->assertEquals('After Update', $response['record']['title']);
        $this->assertEquals(20.0, $response['record']['data']['price']);
    }

    public function test_set_record_values_tool(): void
    {
        $createTool = new CreateRecordTool;
        $created = json_decode($createTool->handle(new Request([
            'entity' => 'product',
            'title' => 'Values Test',
            'field_values' => ['price' => 50.0],
        ])), true);

        $setValuesTool = new SetRecordValuesTool;
        $response = json_decode($setValuesTool->handle(new Request([
            'entity' => 'product',
            'record' => (string) $created['record']['id'],
            'field_values' => [
                'color' => 'Navy Blue',
                'price' => 75.0,
            ],
        ])), true);

        $this->assertEquals('Navy Blue', $response['record_data']['color']);
        $this->assertEquals(75.0, $response['record_data']['price']);
    }

    public function test_publish_and_unpublish_record_tools(): void
    {
        $createTool = new CreateRecordTool;
        $created = json_decode($createTool->handle(new Request([
            'entity' => 'product',
            'title' => 'Publish Lifecycle',
            'status' => 'draft',
        ])), true);

        $recordId = (string) $created['record']['id'];

        $publishTool = new PublishRecordTool;
        $publishResponse = json_decode($publishTool->handle(new Request([
            'entity' => 'product',
            'record' => $recordId,
        ])), true);
        $this->assertEquals('published', $publishResponse['record']['status']);

        $unpublishTool = new UnpublishRecordTool;
        $unpublishResponse = json_decode($unpublishTool->handle(new Request([
            'entity' => 'product',
            'record' => $recordId,
        ])), true);
        $this->assertEquals('draft', $unpublishResponse['record']['status']);
    }

    public function test_delete_record_tool_soft_deletes_and_force_deletes(): void
    {
        $createTool = new CreateRecordTool;
        $created = json_decode($createTool->handle(new Request([
            'entity' => 'product',
            'title' => 'To Soft Delete',
        ])), true);

        $recordId = (string) $created['record']['id'];

        $deleteTool = new DeleteRecordTool;
        $request = new Request([
            'entity' => 'product',
            'record' => $recordId,
        ]);

        $approval = $deleteTool->shouldRequestApproval($request);
        $this->assertNotNull($approval);

        $deleteTool->withoutApproval();
        $response = $deleteTool->handle($request);
        $this->assertStringContainsString('soft-deleted', $response);
        $this->assertSoftDeleted('ff_entity_records', ['id' => $recordId]);

        // Force delete
        $forceRequest = new Request([
            'entity' => 'product',
            'record' => $recordId,
            'force' => true,
        ]);
        $forceResponse = $deleteTool->handle($forceRequest);
        $this->assertStringContainsString('permanently deleted', $forceResponse);
        $this->assertDatabaseMissing('ff_entity_records', ['id' => $recordId]);
    }
}
