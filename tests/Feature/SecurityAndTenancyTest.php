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
use IvanMercedes\FlexFields\Services\EntityDomainService;
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

    public function test_slug_does_not_collide_between_different_tenants(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);

        // Tenant 1 creates entity "Products"
        $entityTenant1 = Entity::create([
            'name' => 'Products',
            'tenant_id' => 1,
        ]);

        $this->assertEquals('products', $entityTenant1->slug);
        $this->assertEquals(1, $entityTenant1->tenant_id);

        // Tenant 2 creates entity "Products" with same name
        $entityTenant2 = Entity::create([
            'name' => 'Products',
            'tenant_id' => 2,
        ]);

        // It should NOT collide with Tenant 1's entity; Tenant 2 should also get 'products'
        $this->assertEquals('products', $entityTenant2->slug);
        $this->assertEquals(2, $entityTenant2->tenant_id);

        $this->assertDatabaseHas('ff_entities', [
            'id' => $entityTenant1->id,
            'tenant_id' => 1,
            'slug' => 'products',
        ]);
        $this->assertDatabaseHas('ff_entities', [
            'id' => $entityTenant2->id,
            'tenant_id' => 2,
            'slug' => 'products',
        ]);
    }

    public function test_slug_auto_increments_when_same_tenant_has_entity_with_same_name(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);

        // Tenant 1 creates first entity
        $entity1 = Entity::create([
            'name' => 'Products',
            'tenant_id' => 1,
        ]);
        $this->assertEquals('products', $entity1->slug);

        // Tenant 1 creates second entity with same name -> slug should be 'products-1'
        $entity2 = Entity::create([
            'name' => 'Products',
            'tenant_id' => 1,
        ]);
        $this->assertEquals('products-1', $entity2->slug);

        // Tenant 1 creates third entity with same name -> slug should be 'products-2'
        $entity3 = Entity::create([
            'name' => 'Products',
            'tenant_id' => 1,
        ]);
        $this->assertEquals('products-2', $entity3->slug);

        // Meanwhile, Tenant 2 creates entity with same name -> gets 'products'
        $entityTenant2 = Entity::create([
            'name' => 'Products',
            'tenant_id' => 2,
        ]);
        $this->assertEquals('products', $entityTenant2->slug);

        // Tenant 2 creates another entity with same name -> gets 'products-1'
        $entityTenant2Second = Entity::create([
            'name' => 'Products',
            'tenant_id' => 2,
        ]);
        $this->assertEquals('products-1', $entityTenant2Second->slug);
    }

    public function test_entity_domain_service_slug_handling_across_tenants(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);

        $service = app(EntityDomainService::class);

        // Create for Tenant 10
        $t10Entity = $service->create(['name' => 'Invoices'], tenantId: 10);
        $this->assertEquals('invoices', $t10Entity->slug);
        $this->assertEquals(10, $t10Entity->tenant_id);

        // Create for Tenant 20 with same name -> no collision
        $t20Entity = $service->create(['name' => 'Invoices'], tenantId: 20);
        $this->assertEquals('invoices', $t20Entity->slug);
        $this->assertEquals(20, $t20Entity->tenant_id);

        // Create again for Tenant 10 -> auto increments to 'invoices-1'
        $t10Entity2 = $service->create(['name' => 'Invoices'], tenantId: 10);
        $this->assertEquals('invoices-1', $t10Entity2->slug);
    }

    public function test_entity_generate_unique_slug_helper(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);

        Entity::create(['name' => 'Orders', 'slug' => 'orders', 'tenant_id' => 5]);

        // Same tenant should get next suffix
        $slugSameTenant = Entity::generateUniqueSlug('Orders', tenantId: 5);
        $this->assertEquals('orders-1', $slugSameTenant);

        // Different tenant should get clean slug
        $slugDifferentTenant = Entity::generateUniqueSlug('Orders', tenantId: 6);
        $this->assertEquals('orders', $slugDifferentTenant);
    }

    public function test_slug_auto_increments_when_tenancy_is_disabled(): void
    {
        config(['flex-fields.tenancy.enabled' => false]);

        $e1 = Entity::create(['name' => 'Single Tenant Entity']);
        $this->assertEquals('single-tenant-entity', $e1->slug);

        $e2 = Entity::create(['name' => 'Single Tenant Entity']);
        $this->assertEquals('single-tenant-entity-1', $e2->slug);

        $e3 = Entity::create(['name' => 'Single Tenant Entity']);
        $this->assertEquals('single-tenant-entity-2', $e3->slug);
    }

    public function test_explicit_duplicate_slug_auto_increments_on_create(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);

        $e1 = Entity::create(['name' => 'Items', 'slug' => 'items', 'tenant_id' => 99]);
        $this->assertEquals('items', $e1->slug);

        // Explicitly providing 'items' again for tenant 99
        $e2 = Entity::create(['name' => 'Items', 'slug' => 'items', 'tenant_id' => 99]);
        $this->assertEquals('items-1', $e2->slug);
    }
}
