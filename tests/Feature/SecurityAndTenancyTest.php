<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Ai\AiContext;
use IvanMercedes\FlexFields\Ai\Tools\CreateEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\GetEntitySchemaTool;
use IvanMercedes\FlexFields\Ai\Tools\ListEntitiesTool;
use IvanMercedes\FlexFields\Ai\Tools\PublishRecordTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class SecurityAndTenancyTest extends TestCase
{
    public function test_read_only_context_blocks_mutations(): void
    {
        $context = (new AiContext)->readOnly(true);

        $createEntityTool = (new CreateEntityTool)->setContext($context);
        $result = $createEntityTool->handle(new Request(['name' => 'Secret Entity']));
        $this->assertStringContainsString('read-only mode', $result);
        $this->assertDatabaseMissing('ff_entities', ['name' => 'Secret Entity']);

        $deleteEntityTool = (new DeleteEntityTool)->setContext($context);
        $resultDelete = $deleteEntityTool->handle(new Request(['entity' => 'any-entity']));
        $this->assertStringContainsString('read-only mode', $resultDelete);

        $createRecordTool = (new CreateRecordTool)->setContext($context);
        $resultRecord = $createRecordTool->handle(new Request(['entity' => 'any-entity', 'title' => 'New']));
        $this->assertStringContainsString('read-only mode', $resultRecord);
    }

    public function test_allowed_entities_whitelist_blocks_unauthorized_access(): void
    {
        Entity::create(['name' => 'Public Posts', 'slug' => 'public-posts']);
        Entity::create(['name' => 'Private Invoices', 'slug' => 'private-invoices']);

        $context = (new AiContext)->allowEntities(['public-posts']);

        // Schema introspection on allowed entity succeeds
        $schemaTool = (new GetEntitySchemaTool)->setContext($context);
        $allowedResponse = $schemaTool->handle(new Request(['entity' => 'public-posts']));
        $this->assertStringContainsString('public-posts', $allowedResponse);

        // Schema introspection on forbidden entity is rejected
        $forbiddenResponse = $schemaTool->handle(new Request(['entity' => 'private-invoices']));
        $this->assertStringContainsString('not permitted', $forbiddenResponse);

        // Mutation on forbidden entity is rejected
        $recordTool = (new CreateRecordTool)->setContext($context);
        $rejectedRecord = $recordTool->handle(new Request([
            'entity' => 'private-invoices',
            'title' => 'Fraudulent Invoice',
        ]));
        $this->assertStringContainsString('not permitted', $rejectedRecord);
    }

    public function test_multi_tenancy_isolation(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);

        // Tenant 1 entity
        $entityTenant1 = Entity::create([
            'name' => 'Tenant 1 Product',
            'slug' => 't1-product',
            'tenant_id' => 1,
        ]);
        $entityTenant1->records()->create([
            'tenant_id' => 1,
            'title' => 'T1 Secret Item',
            'slug' => 't1-secret',
            'status' => 'published',
        ]);

        // Tenant 2 entity
        $entityTenant2 = Entity::create([
            'name' => 'Tenant 2 Product',
            'slug' => 't2-product',
            'tenant_id' => 2,
        ]);

        // Context for Tenant 2
        $contextTenant2 = (new AiContext)->forTenant(2);

        // List entities should only see Tenant 2
        $listTool = (new ListEntitiesTool)->setContext($contextTenant2);
        $listResponse = json_decode($listTool->handle(new Request([])), true);
        $this->assertEquals(1, $listResponse['count']);
        $this->assertEquals('t2-product', $listResponse['entities'][0]['slug']);

        // Tenant 2 trying to introspect Tenant 1's entity fails
        $schemaTool = (new GetEntitySchemaTool)->setContext($contextTenant2);
        $schemaResponse = $schemaTool->handle(new Request(['entity' => 't1-product']));
        $this->assertStringContainsString('not found or not accessible', $schemaResponse);

        // Tenant 2 trying to modify Tenant 1's records fails
        $publishTool = (new PublishRecordTool)->setContext($contextTenant2);
        $publishResponse = $publishTool->handle(new Request([
            'entity' => 't1-product',
            'record' => 't1-secret',
        ]));
        $this->assertStringContainsString('not found', $publishResponse);
    }

    public function test_validation_errors_are_caught_and_reported(): void
    {
        $tool = new CreateEntityTool;

        $this->expectException(ValidationException::class);
        $tool->handle(new Request([])); // Missing required 'name'
    }
}
