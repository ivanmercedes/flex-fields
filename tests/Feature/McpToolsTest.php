<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\FlexFieldsAi;
use IvanMercedes\FlexFields\Mcp\FlexFieldsMcpTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Mcp\Request;

class McpToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Entity::create(['name' => 'Product', 'slug' => 'product']);
    }

    public function test_tools_are_named_and_annotated_for_mcp(): void
    {
        $tools = $this->tools();

        $this->assertArrayHasKey('get_entity_schema', $tools);
        $this->assertSame('Get Entity Schema', $tools['get_entity_schema']->title());
        $this->assertTrue($tools['list_records']->annotations()['readOnlyHint']);
        $this->assertTrue($tools['delete_record']->annotations()['destructiveHint']);
        $this->assertFalse($tools['create_record']->annotations()['readOnlyHint']);
        $this->assertArrayHasKey('entity', $tools['get_entity_schema']->toArray()['inputSchema']['properties']);
    }

    public function test_results_and_failures_map_to_mcp_responses(): void
    {
        $tools = $this->tools();

        $schema = $tools['get_entity_schema']->handle(new Request(['entity' => 'product']));
        $this->assertFalse($schema->isError());
        $this->assertStringContainsString('"slug": "product"', (string) $schema->content());

        $this->assertTrue($tools['get_record']->handle(new Request(['entity' => 'product', 'record' => 'missing']))->isError());
        $this->assertTrue($tools['get_record']->handle(new Request(['entity' => 'product']))->isError(), 'Invalid arguments are tool errors.');
    }

    public function test_the_catalog_follows_the_builder_configuration(): void
    {
        $names = array_map(fn ($tool) => $tool->name(), FlexFieldsAi::configure()->categories(AiToolCategory::Read)->mcpTools());

        $this->assertContains('list_records', $names);
        $this->assertNotContains('create_record', $names);
    }

    /**
     * @return array<string, FlexFieldsMcpTool>
     */
    protected function tools(): array
    {
        return collect(FlexFieldsAi::configure()->withoutApprovals()->mcpTools())->keyBy(fn (FlexFieldsMcpTool $tool) => $tool->name())->all();
    }
}
