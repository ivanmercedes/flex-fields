<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Ai\AiContext;
use IvanMercedes\FlexFields\Ai\Tools\CreateFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateFieldTool;
use IvanMercedes\FlexFields\Http\FlexApi;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityCategory;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Support\UploadPaths;
use IvanMercedes\FlexFields\Tests\Fixtures\User;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class TenantBoundariesTest extends TestCase
{
    protected function tearDown(): void
    {
        FlexApi::resolveTenantUsing(null);

        parent::tearDown();
    }

    public function test_expanded_relations_leave_out_entities_the_api_cannot_read(): void
    {
        $product = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $product->customFields()->create(['label' => 'Brand', 'key' => 'brand', 'type' => 'relationship', 'settings' => ['related_entity' => 'brand']]);
        Entity::create(['name' => 'Brand', 'slug' => 'brand']);
        $brand = (new RecordDomainService)->create('brand', ['title' => 'Apple']);
        (new RecordDomainService)->create('product', ['title' => 'iPhone'], ['brand' => $brand->id]);
        $user = $this->makeUser();

        $this->actingAs($user)->getJson('/api/flex/product/iphone?include=relations')
            ->assertOk()
            ->assertJsonPath('data.relations.brand.0.title', 'Apple');

        config(['flex-fields.api.allowed_entities' => ['product']]);

        $this->actingAs($user)->getJson('/api/flex/product/iphone?include=relations.values')
            ->assertOk()
            ->assertJsonMissingPath('data.relations.brand');
    }

    public function test_ai_field_tools_cannot_link_to_entities_outside_the_context(): void
    {
        $posts = Entity::create(['name' => 'Posts', 'slug' => 'posts']);
        $posts->customFields()->create(['label' => 'Author', 'key' => 'author', 'type' => 'relationship', 'settings' => ['related_entity' => 'posts']]);
        Entity::create(['name' => 'Invoices', 'slug' => 'invoices']);
        $context = (new AiContext)->allowEntities(['posts']);

        $created = (new CreateFieldTool)->setContext($context)->handle(new Request([
            'entity' => 'posts', 'label' => 'Invoice', 'type' => 'relationship', 'settings' => ['related_entity' => 'invoices'],
        ]));
        $updated = (new UpdateFieldTool)->setContext($context)->handle(new Request([
            'entity' => 'posts', 'field' => 'author', 'settings' => ['related_entity' => 'invoices'],
        ]));

        $this->assertStringContainsString('not permitted', (string) $created);
        $this->assertStringContainsString('not permitted', (string) $updated);
        $this->assertDatabaseMissing('ff_custom_fields', ['key' => 'invoice']);
        $this->assertSame('posts', $posts->customFields()->where('key', 'author')->first()->settings['related_entity']);
    }

    public function test_categories_cannot_belong_to_an_entity_of_another_tenant(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);
        $foreign = Entity::create(['name' => 'Orders', 'slug' => 'orders', 'tenant_id' => 2]);

        $this->expectException(ValidationException::class);

        EntityCategory::create(['tenant_id' => 1, 'entity_id' => $foreign->id, 'name' => 'Stolen']);
    }

    public function test_file_values_must_point_to_the_files_of_their_tenant(): void
    {
        config([
            'flex-fields.tenancy.enabled' => true,
            'flex-fields.tenancy.tenant_model' => User::class,
            'flex-fields.uploads.tenant_key_attribute' => 'name',
        ]);
        $acme = $this->makeUser('acme');
        $this->makeUser('globex');
        $entity = Entity::create(['name' => 'Product', 'slug' => 'product', 'tenant_id' => $acme->id]);
        $entity->customFields()->create(['tenant_id' => $acme->id, 'label' => 'Cover', 'key' => 'cover', 'type' => 'image']);

        $this->assertSame('flex-fields/acme', UploadPaths::tenantRoot($acme->id));
        $this->assertTrue(UploadPaths::allows($acme->id, 'flex-fields/acme/product/cover/a.png'));
        $this->assertTrue(UploadPaths::allows($acme->id, 'https://cdn.example.com/a.png'));
        $this->assertFalse(UploadPaths::allows($acme->id, 'flex-fields/acme/../globex/a.png'));
        $this->assertFalse(UploadPaths::allows($acme->id, 'flex-fields/acme-old/a.png'));

        (new RecordDomainService)->create($entity->id, ['title' => 'Own'], ['cover' => 'flex-fields/acme/product/cover/a.png'], tenantId: $acme->id);

        $this->expectException(ValidationException::class);

        (new RecordDomainService)->create($entity->id, ['title' => 'Borrowed'], ['cover' => 'flex-fields/globex/product/cover/secret.png'], tenantId: $acme->id);
    }

    public function test_database_errors_are_not_exposed(): void
    {
        Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->app->instance(RecordDomainService::class, new class extends RecordDomainService
        {
            public function create(int | string $entityIdOrSlug, array $attributes = [], array $fieldValues = [], array $categoryIds = [], int | string | null $tenantId = null): never
            {
                throw new QueryException('sqlite', 'insert into secret_table', [], new Exception('secret details'));
            }
        });

        $this->actingAs($this->makeUser())->postJson('/api/flex/product', ['title' => 'X'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The record could not be saved.');

        $tool = new class extends CreateRecordTool
        {
            public function describe(QueryException $e): string
            {
                return $this->describeException($e);
            }
        };

        $this->assertStringNotContainsString('secret', $tool->describe(new QueryException('sqlite', 'insert into secret_table', [], new Exception('secret details'))));
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('flex-fields.api.enabled', true);
        $app['config']->set('flex-fields.api.middleware', ['api', 'auth']);
    }
}
