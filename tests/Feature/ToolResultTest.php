<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\AiContext;
use IvanMercedes\FlexFields\Ai\ToolResult;
use IvanMercedes\FlexFields\Ai\Tools\CreateRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\GetEntitySchemaTool;
use IvanMercedes\FlexFields\Ai\Tools\GetRecordTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class ToolResultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Entity::create(['name' => 'Product', 'slug' => 'product']);
    }

    public function test_successful_runs_are_not_errors(): void
    {
        $result = (new GetEntitySchemaTool)->run(new Request(['entity' => 'product']));

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertFalse($result->isError);
        $this->assertStringContainsString('"slug": "product"', (string) $result);
    }

    public function test_domain_failures_are_errors(): void
    {
        $result = (new GetRecordTool)->run(new Request(['entity' => 'product', 'record' => 'missing']));

        $this->assertTrue($result->isError);
        $this->assertStringContainsString('missing', $result->content);
    }

    public function test_rejected_operations_are_errors(): void
    {
        $tool = (new CreateRecordTool)->setContext((new AiContext)->readOnly());

        $this->assertTrue($tool->run(new Request(['entity' => 'product', 'title' => 'Phone']))->isError);
        $this->assertSame(0, Entity::where('slug', 'product')->first()->records()->count());
    }

    public function test_handle_keeps_returning_text_for_the_ai_sdk(): void
    {
        $text = (new GetRecordTool)->handle(new Request(['entity' => 'product', 'record' => 'missing']));

        $this->assertIsString($text);
        $this->assertStringStartsWith('Error retrieving record', $text);
    }

    public function test_tools_expose_a_name_and_title(): void
    {
        $tool = new GetEntitySchemaTool;

        $this->assertSame('get_entity_schema', $tool->toolName());
        $this->assertSame('Get Entity Schema', $tool->toolTitle());
    }
}
