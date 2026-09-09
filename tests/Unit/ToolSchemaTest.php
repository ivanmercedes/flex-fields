<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Unit;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use IvanMercedes\FlexFields\Ai\FlexFieldsAi;
use IvanMercedes\FlexFields\Tests\TestCase;

class ToolSchemaTest extends TestCase
{
    public function test_all_tools_have_descriptions_and_valid_schemas(): void
    {
        $tools = FlexFieldsAi::allTools();
        $schemaFactory = new JsonSchemaTypeFactory;

        foreach ($tools as $tool) {
            $desc = $tool->description();
            $this->assertNotEmpty($desc, 'Tool ' . get_class($tool) . ' must have a non-empty description.');

            $schema = $tool->schema($schemaFactory);
            $this->assertIsArray($schema, 'Tool ' . get_class($tool) . ' must return an array from schema().');
        }
    }
}
