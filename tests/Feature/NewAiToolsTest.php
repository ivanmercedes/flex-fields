<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\AiContext;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\FlexFieldsAi;
use IvanMercedes\FlexFields\Ai\Tools\CreateFieldGroupTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteFieldGroupTool;
use IvanMercedes\FlexFields\Ai\Tools\GetEntitySchemaTool;
use IvanMercedes\FlexFields\Ai\Tools\ListRecordRevisionsTool;
use IvanMercedes\FlexFields\Ai\Tools\RestoreRecordRevisionTool;
use IvanMercedes\FlexFields\Ai\Tools\SetRecordValuesTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateFieldGroupTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateFieldTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class NewAiToolsTest extends TestCase
{
    protected Entity $entity;

    protected AiContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->entity->customFields()->create(['label' => 'Price', 'key' => 'price', 'type' => 'number']);
        $this->entity->customFields()->create(['label' => 'Meta Title', 'key' => 'meta_title', 'type' => 'text']);
        Entity::create(['name' => 'Brand', 'slug' => 'brand']);

        $this->context = (new AiContext)->withoutApprovals();
    }

    public function test_new_tools_are_registered_in_their_categories(): void
    {
        $classes = FlexFieldsAi::getRegisteredToolClasses();

        foreach ([CreateFieldGroupTool::class, UpdateFieldGroupTool::class, DeleteFieldGroupTool::class, ListRecordRevisionsTool::class, RestoreRecordRevisionTool::class] as $class) {
            $this->assertContains($class, $classes);
        }

        $read = array_map(fn ($tool) => $tool::class, FlexFieldsAi::readTools());
        $this->assertContains(ListRecordRevisionsTool::class, $read);

        $destructive = array_map(fn ($tool) => $tool::class, FlexFieldsAi::destructiveTools());
        $this->assertContains(DeleteFieldGroupTool::class, $destructive);

        $this->assertSame([AiToolCategory::Destructive, AiToolCategory::Structure], (new DeleteFieldGroupTool)->toolCategory());
    }

    public function test_field_group_tools_lifecycle(): void
    {
        $created = json_decode((new CreateFieldGroupTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'label' => 'SEO', 'fields' => ['meta_title'], 'is_collapsed' => true,
        ])), true);

        $this->assertSame('seo', $created['group']['key']);
        $this->assertSame(['meta_title'], $created['group']['fields']);

        $updated = json_decode((new UpdateFieldGroupTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'group' => 'seo', 'label' => 'Search', 'fields' => ['price'],
        ])), true);
        $this->assertSame('Search', $updated['group']['label']);
        $this->assertEqualsCanonicalizing(['meta_title', 'price'], $updated['group']['fields']);

        $schema = json_decode((new GetEntitySchemaTool)->setContext($this->context)->handle(new Request(['entity' => 'product'])), true);
        $this->assertSame('seo', $schema['groups'][0]['key']);

        $deleted = (new DeleteFieldGroupTool)->setContext($this->context)->handle(new Request(['entity' => 'product', 'group' => 'seo']));
        $this->assertStringContainsString('ungrouped', $deleted);
        $this->assertDatabaseCount('ff_custom_fields', 2);
    }

    public function test_create_field_tool_accepts_new_types_settings_and_group(): void
    {
        (new CreateFieldGroupTool)->setContext($this->context)->handle(new Request(['entity' => 'product', 'label' => 'Relations']));

        $response = json_decode((new CreateFieldTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product',
            'label' => 'Brand',
            'type' => 'relationship',
            'group' => 'relations',
            'settings' => [
                'related_entity' => 'brand',
                'show_when' => ['match' => 'all', 'conditions' => [['field' => 'price', 'operator' => 'gt', 'value' => 0]]],
            ],
        ])), true);

        $this->assertSame('relations', $response['field']['group']);
        $this->assertSame('brand', $response['field']['settings']['related_entity']);
        $this->assertSame('gt', $response['field']['settings']['show_when']['conditions'][0]['operator']);

        $error = (new CreateFieldTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'label' => 'Broken', 'type' => 'relationship',
        ]));
        $this->assertStringContainsString('related_entity', $error);

        $updated = json_decode((new UpdateFieldTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'field' => 'meta_title', 'settings' => ['translatable' => true], 'group' => 'relations',
        ])), true);
        $this->assertTrue($updated['field']['settings']['translatable']);
        $this->assertSame('relations', $updated['field']['group']);
    }

    public function test_create_field_tool_schema_lists_all_configured_types(): void
    {
        $description = (new CreateFieldTool)->description();

        foreach (['phone', 'address', 'relationship', 'repeater'] as $type) {
            $this->assertStringContainsString($type, $description);
        }
    }

    public function test_record_tools_return_readable_validation_errors(): void
    {
        $response = (new CreateRecordTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'title' => 'Phone', 'field_values' => ['price' => 'expensive'],
        ]));

        $this->assertStringContainsString('price:', $response);
        $this->assertStringContainsString('must be a number', $response);
    }

    public function test_revision_tools_list_and_restore(): void
    {
        $records = new RecordDomainService;
        $record = $records->create('product', ['title' => 'Phone'], ['price' => 100]);
        (new SetRecordValuesTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'record' => (string) $record->id, 'field_values' => ['price' => 200],
        ]));

        $history = json_decode((new ListRecordRevisionsTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'record' => (string) $record->id,
        ])), true);

        $this->assertSame(2, $history['count']);
        $this->assertSame('updated', $history['revisions'][0]['event']);
        $this->assertSame(['old' => 100, 'new' => 200], $history['revisions'][0]['changes']['fields.price']);

        $original = $history['revisions'][1]['id'];
        $restored = json_decode((new RestoreRecordRevisionTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'record' => (string) $record->id, 'revision_id' => $original,
        ])), true);

        $this->assertEquals(100, $restored['record']['data']['price']);
    }

    public function test_restore_revision_cannot_use_revisions_of_other_records(): void
    {
        $records = new RecordDomainService;
        $a = $records->create('product', ['title' => 'A']);
        $b = $records->create('product', ['title' => 'B']);
        $revisionOfB = $b->revisions()->first();

        $response = (new RestoreRecordRevisionTool)->setContext($this->context)->handle(new Request([
            'entity' => 'product', 'record' => (string) $a->id, 'revision_id' => $revisionOfB->id,
        ]));

        $this->assertStringContainsString('Failed to restore', $response);
    }

    public function test_sensitive_new_tools_require_approval_by_default(): void
    {
        $this->assertNotNull((new DeleteFieldGroupTool)->shouldRequestApproval(new Request(['entity' => 'product', 'group' => 'seo'])));
        $this->assertNotNull((new RestoreRecordRevisionTool)->shouldRequestApproval(new Request(['entity' => 'product', 'record' => '1', 'revision_id' => 1])));

        $withoutApprovals = (new DeleteFieldGroupTool)->setContext($this->context);
        $this->assertNull($withoutApprovals->shouldRequestApproval(new Request(['entity' => 'product', 'group' => 'seo'])));
    }

    public function test_read_only_context_blocks_new_mutating_tools(): void
    {
        $readOnly = (new AiContext)->readOnly();

        foreach ([new CreateFieldGroupTool, new UpdateFieldGroupTool, new DeleteFieldGroupTool, new RestoreRecordRevisionTool] as $tool) {
            $this->assertStringContainsString('read-only', $tool->setContext($readOnly)->handle(new Request(['entity' => 'product'])));
        }
    }
}
