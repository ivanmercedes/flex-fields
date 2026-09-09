<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\Tools\CreateCategoryTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteCategoryTool;
use IvanMercedes\FlexFields\Ai\Tools\ListCategoriesTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateCategoryTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class CategoryToolsTest extends TestCase
{
    protected Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create([
            'name' => 'Products',
            'slug' => 'products',
        ]);
    }

    public function test_create_and_list_categories(): void
    {
        $createTool = new CreateCategoryTool;
        $createResponse = $createTool->handle(new Request([
            'entity' => 'products',
            'name' => 'Beverages',
            'description' => 'Drinks and beers',
        ]));

        $createData = json_decode($createResponse, true);
        $this->assertEquals('Beverages', $createData['category']['name']);
        $this->assertEquals('beverages', $createData['category']['slug']);

        $listTool = new ListCategoriesTool;
        $listResponse = json_decode($listTool->handle(new Request(['entity' => 'products'])), true);
        $this->assertEquals(1, $listResponse['count']);
        $this->assertEquals('beverages', $listResponse['categories'][0]['slug']);
    }

    public function test_update_category(): void
    {
        $category = $this->entity->categories()->create([
            'name' => 'Old Category',
            'slug' => 'old-category',
        ]);

        $updateTool = new UpdateCategoryTool;
        $response = $updateTool->handle(new Request([
            'entity' => 'products',
            'category' => 'old-category',
            'name' => 'Updated Category',
        ]));

        $data = json_decode($response, true);
        $this->assertEquals('Updated Category', $data['category']['name']);
        $this->assertDatabaseHas('ff_entity_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
        ]);
    }

    public function test_delete_category_requires_approval_and_deletes(): void
    {
        $category = $this->entity->categories()->create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
        ]);

        $tool = new DeleteCategoryTool;
        $request = new Request([
            'entity' => 'products',
            'category' => 'to-delete',
        ]);

        $approval = $tool->shouldRequestApproval($request);
        $this->assertNotNull($approval);
        $this->assertStringContainsString('to-delete', $approval->reason ?? '');

        $tool->withoutApproval();
        $result = $tool->handle($request);
        $this->assertStringContainsString('successfully deleted', $result);
        $this->assertDatabaseMissing('ff_entity_categories', ['id' => $category->id]);
    }
}
