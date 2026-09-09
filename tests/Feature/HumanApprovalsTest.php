<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\FlexFieldsAi;
use IvanMercedes\FlexFields\Ai\Tools\DeleteCategoryTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteRecordTool;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class HumanApprovalsTest extends TestCase
{
    public function test_all_destructive_tools_require_approval_by_default(): void
    {
        $entityTool = new DeleteEntityTool;
        $this->assertNotNull($entityTool->shouldRequestApproval(new Request(['entity' => 'demo'])));

        $fieldTool = new DeleteFieldTool;
        $this->assertNotNull($fieldTool->shouldRequestApproval(new Request(['entity' => 'demo', 'field' => 'price'])));

        $catTool = new DeleteCategoryTool;
        $this->assertNotNull($catTool->shouldRequestApproval(new Request(['entity' => 'demo', 'category' => 'cat'])));

        $recordTool = new DeleteRecordTool;
        $this->assertNotNull($recordTool->shouldRequestApproval(new Request(['entity' => 'demo', 'record' => '1'])));
    }

    public function test_custom_approval_reason_can_be_set(): void
    {
        $tool = new DeleteEntityTool;
        $tool->requireApproval('Requires VP engineering sign-off.');

        $approval = $tool->shouldRequestApproval(new Request(['entity' => 'production_data']));
        $this->assertNotNull($approval);
        $this->assertEquals('Requires VP engineering sign-off.', $approval->reason);
    }

    public function test_without_approval_can_be_configured_globally(): void
    {
        $tools = FlexFieldsAi::configure()->withoutApprovals()->destructiveTools();

        foreach ($tools as $tool) {
            $this->assertNull($tool->shouldRequestApproval(new Request([
                'entity' => 'test',
                'field' => 'test',
                'category' => 'test',
                'record' => '1',
            ])));
        }
    }
}
