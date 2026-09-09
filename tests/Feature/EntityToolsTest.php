<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\Tools\CreateEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\ListEntitiesTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateEntityTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class EntityToolsTest extends TestCase
{
    public function test_list_entities_tool_returns_json_list(): void
    {
        Entity::create(['name' => 'Products', 'slug' => 'products', 'is_active' => true]);
        Entity::create(['name' => 'Blog Posts', 'slug' => 'blog-posts', 'is_active' => false]);

        $tool = new ListEntitiesTool;
        $response = $tool->handle(new Request(['active_only' => true]));

        $data = json_decode($response, true);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals('products', $data['entities'][0]['slug']);

        $responseAll = $tool->handle(new Request(['active_only' => false]));
        $dataAll = json_decode($responseAll, true);
        $this->assertEquals(2, $dataAll['count']);
    }

    public function test_create_entity_tool(): void
    {
        $tool = new CreateEntityTool;
        $response = $tool->handle(new Request([
            'name' => 'Craft Beer',
            'description' => 'Collection of beers',
            'icon' => 'heroicon-o-beer',
            'color' => '#f59e0b',
        ]));

        $data = json_decode($response, true);
        $this->assertArrayHasKey('entity', $data);
        $this->assertEquals('craft-beer', $data['entity']['slug']);
        $this->assertDatabaseHas('ff_entities', ['slug' => 'craft-beer']);
    }

    public function test_update_entity_tool(): void
    {
        $entity = Entity::create(['name' => 'Old Name', 'slug' => 'old-name']);

        $tool = new UpdateEntityTool;
        $response = $tool->handle(new Request([
            'entity' => 'old-name',
            'name' => 'New Name',
            'description' => 'Updated description',
        ]));

        $data = json_decode($response, true);
        $this->assertEquals('New Name', $data['entity']['name']);
        $this->assertDatabaseHas('ff_entities', [
            'id' => $entity->id,
            'name' => 'New Name',
        ]);
    }

    public function test_delete_entity_tool_deletes_entity_and_requires_approval(): void
    {
        $entity = Entity::create(['name' => 'To Delete', 'slug' => 'to-delete']);

        $tool = new DeleteEntityTool;
        $request = new Request(['entity' => 'to-delete']);

        $approval = $tool->shouldRequestApproval($request);
        $this->assertNotNull($approval);
        $this->assertStringContainsString('to-delete', $approval->reason ?? '');

        // Execute without approval
        $tool->withoutApproval();
        $this->assertNull($tool->shouldRequestApproval($request));

        $result = $tool->handle($request);
        $this->assertStringContainsString('permanently deleted', $result);
        $this->assertDatabaseMissing('ff_entities', ['id' => $entity->id]);
    }
}
