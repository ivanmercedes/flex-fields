<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Unit;

use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\FlexFieldsAi;
use IvanMercedes\FlexFields\Ai\Tools\CreateEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\GetEntitySchemaTool;
use IvanMercedes\FlexFields\Ai\Tools\ListEntitiesTool;
use IvanMercedes\FlexFields\Facades\FlexFields;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;

class FlexFieldsAiTest extends TestCase
{
    public function test_ai_integration_is_available(): void
    {
        $this->assertTrue(FlexFieldsAi::isAvailable());
    }

    public function test_it_discovers_all_registered_tools(): void
    {
        $tools = FlexFieldsAi::tools();

        $this->assertNotEmpty($tools);
        $this->assertContainsOnlyInstancesOf(Tool::class, $tools);

        $toolClasses = array_map(fn ($t) => get_class($t), $tools);
        $this->assertContains(ListEntitiesTool::class, $toolClasses);
        $this->assertContains(GetEntitySchemaTool::class, $toolClasses);
        $this->assertContains(CreateEntityTool::class, $toolClasses);
        $this->assertContains(DeleteEntityTool::class, $toolClasses);
    }

    public function test_it_filters_tools_by_category(): void
    {
        $readTools = FlexFieldsAi::readTools();
        $this->assertNotEmpty($readTools);
        foreach ($readTools as $tool) {
            $cats = (array) $tool->toolCategory();
            $this->assertTrue(in_array(AiToolCategory::Read, $cats, true) || in_array('read', $cats, true));
        }

        $destructiveTools = FlexFieldsAi::destructiveTools();
        $this->assertNotEmpty($destructiveTools);
        foreach ($destructiveTools as $tool) {
            $cats = (array) $tool->toolCategory();
            $this->assertTrue(in_array(AiToolCategory::Destructive, $cats, true) || in_array('destructive', $cats, true));
        }
    }

    public function test_destructive_tools_implement_approvable(): void
    {
        $destructiveTools = FlexFieldsAi::destructiveTools();

        foreach ($destructiveTools as $tool) {
            $this->assertInstanceOf(Approvable::class, $tool);
        }
    }

    public function test_facade_provides_ai_builder(): void
    {
        $tools = FlexFields::ai()->readOnly()->readTools();

        $this->assertNotEmpty($tools);
        $this->assertTrue($tools[0]->context()->isReadOnly());
    }
}
