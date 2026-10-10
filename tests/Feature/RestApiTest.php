<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use IvanMercedes\FlexFields\Http\FlexApi;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Tests\Fixtures\User;
use IvanMercedes\FlexFields\Tests\TestCase;

class RestApiTest extends TestCase
{
    protected Entity $entity;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->entity->customFields()->create(['label' => 'Price', 'key' => 'price', 'type' => 'number', 'is_required' => true]);
        $this->entity->customFields()->create(['label' => 'Brand', 'key' => 'brand', 'type' => 'relationship', 'settings' => ['related_entity' => 'brand']]);
        $this->entity->categories()->create(['name' => 'Phones', 'slug' => 'phones']);
        Entity::create(['name' => 'Brand', 'slug' => 'brand']);

        $this->user = $this->makeUser();
    }

    protected function tearDown(): void
    {
        FlexApi::resolveTenantUsing(null);

        parent::tearDown();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/flex/entities')->assertUnauthorized();
    }

    public function test_lists_entities_and_schema(): void
    {
        Entity::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $this->actingAs($this->user)->getJson('/api/flex/entities')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.slug', 'product')
            ->assertJsonPath('data.1.links.schema', url('api/flex/product/schema'));

        $this->actingAs($this->user)->getJson('/api/flex/product/schema')
            ->assertOk()
            ->assertJsonPath('data.slug', 'product')
            ->assertJsonPath('data.fields.0.key', 'price')
            ->assertJsonPath('data.fields.1.relationship.entity', 'brand');

        $this->actingAs($this->user)->getJson('/api/flex/hidden/schema')->assertNotFound();
    }

    public function test_full_crud_lifecycle(): void
    {
        $brand = (new RecordDomainService)->create('brand', ['title' => 'Apple']);

        $created = $this->actingAs($this->user)->postJson('/api/flex/product', [
            'title' => 'iPhone',
            'values' => ['price' => 999, 'brand' => $brand->id],
            'categories' => ['phones'],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'iPhone')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.values.price', 999)
            ->assertJsonPath('data.categories.0.slug', 'phones');

        $id = $created->json('data.id');

        $this->actingAs($this->user)->getJson('/api/flex/product/iphone?include=relations')
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.relations.brand.0.title', 'Apple');

        $this->actingAs($this->user)->patchJson("/api/flex/product/{$id}", ['data' => ['price' => 1099], 'status' => 'draft'])
            ->assertOk()
            ->assertJsonPath('data.values.price', 1099)
            ->assertJsonPath('data.status', 'draft');

        $this->actingAs($this->user)->getJson('/api/flex/product?status=draft&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->user)->deleteJson("/api/flex/product/{$id}")->assertOk();
        $this->actingAs($this->user)->getJson("/api/flex/product/{$id}")->assertNotFound();
        $this->actingAs($this->user)->getJson('/api/flex/product?trashed=only')->assertJsonCount(1, 'data');

        $this->actingAs($this->user)->postJson("/api/flex/product/{$id}/restore")->assertOk()->assertJsonPath('data.deleted_at', null);

        $this->actingAs($this->user)->deleteJson("/api/flex/product/{$id}?force=1")->assertOk();
        $this->assertDatabaseMissing('ff_entity_records', ['id' => $id]);
    }

    public function test_validation_errors_return_422(): void
    {
        $this->actingAs($this->user)->postJson('/api/flex/product', ['title' => 'No price'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price');

        $this->actingAs($this->user)->postJson('/api/flex/product', ['title' => 'Bad', 'values' => ['price' => 'abc']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price');

        $this->actingAs($this->user)->postJson('/api/flex/product', ['title' => 'Bad', 'status' => 'live', 'values' => ['price' => 1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($this->user)->postJson('/api/flex/product', ['title' => 'Bad', 'values' => ['price' => 1], 'categories' => ['missing']])
            ->assertUnprocessable();

        $this->actingAs($this->user)->getJson('/api/flex/missing')->assertNotFound();
    }

    public function test_allowed_entities_restricts_access(): void
    {
        config(['flex-fields.api.allowed_entities' => ['brand']]);

        $this->actingAs($this->user)->getJson('/api/flex/product')->assertNotFound();
        $this->actingAs($this->user)->getJson('/api/flex/entities')->assertJsonCount(1, 'data');
    }

    public function test_tenancy_requires_a_resolver_and_isolates_tenants(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);

        $this->actingAs($this->user)->getJson('/api/flex/entities')->assertForbidden();

        $tenantA = Entity::create(['name' => 'Orders', 'slug' => 'orders', 'tenant_id' => 1]);
        Entity::create(['name' => 'Orders', 'slug' => 'orders', 'tenant_id' => 2]);
        (new RecordDomainService)->create($tenantA->id, ['title' => 'Order A'], tenantId: 1);

        FlexApi::resolveTenantUsing(fn () => 2);

        $this->actingAs($this->user)->getJson('/api/flex/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->user)->getJson("/api/flex/{$tenantA->id}")->assertNotFound();

        $this->actingAs($this->user)->postJson('/api/flex/orders', ['title' => 'Order B', 'tenant_id' => 1])->assertCreated();
        $this->assertDatabaseHas('ff_entity_records', ['title' => 'Order B', 'tenant_id' => 2]);
    }

    public function test_sanctum_token_abilities_are_enforced(): void
    {
        $this->user->tokenAbilities = ['flex:read'];

        $this->actingAs($this->user)->getJson('/api/flex/product')->assertOk();
        $this->actingAs($this->user)->postJson('/api/flex/product', ['title' => 'X', 'values' => ['price' => 1]])->assertForbidden();
        $this->actingAs($this->user)->deleteJson('/api/flex/product/1')->assertForbidden();
    }

    public function test_per_entity_permissions_are_enforced(): void
    {
        config(['flex-fields.permissions.enabled' => true]);

        $granted = ['view_any_flex_product', 'view_flex_product', 'create_flex_product'];
        Gate::before(fn ($user, string $ability) => in_array($ability, $granted, true) ? true : null);

        $response = $this->actingAs($this->user)->postJson('/api/flex/product', ['title' => 'Draft', 'values' => ['price' => 1]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->actingAs($this->user)->postJson('/api/flex/product', ['title' => 'Live', 'status' => 'published', 'values' => ['price' => 1]])
            ->assertForbidden();

        $id = $response->json('data.id');
        $this->actingAs($this->user)->patchJson("/api/flex/product/{$id}", ['title' => 'Nope'])->assertForbidden();
        $this->actingAs($this->user)->deleteJson("/api/flex/product/{$id}")->assertForbidden();

        $this->actingAs($this->user)->getJson('/api/flex/entities')->assertJsonCount(1, 'data');
        $this->actingAs($this->user)->getJson('/api/flex/brand')->assertForbidden();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('flex-fields.api.enabled', true);
        $app['config']->set('flex-fields.api.middleware', ['api', 'auth']);
    }
}
