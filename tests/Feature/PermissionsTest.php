<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use IvanMercedes\FlexFields\Ai\AiContext;
use IvanMercedes\FlexFields\Ai\Tools\CreateFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\GetEntitySchemaTool;
use IvanMercedes\FlexFields\Ai\Tools\ListEntitiesTool;
use IvanMercedes\FlexFields\Ai\Tools\PublishRecordTool;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Resources\EntityResource;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class PermissionsTest extends TestCase
{
    /** @var array<int, string> */
    protected array $granted = [];

    protected Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create(['name' => 'Blog Posts', 'slug' => 'blog-posts']);
        $this->entity->customFields()->create(['label' => 'Body', 'key' => 'body', 'type' => 'textarea']);

        Gate::before(fn ($user, string $ability) => in_array($ability, $this->granted, true) ? true : null);
    }

    public function test_permission_names_and_listing(): void
    {
        $this->assertSame('view_any_flex_blog_posts', FlexPermissions::permissionName('view_any', $this->entity));
        $this->assertSame('publish_flex_blog_posts', FlexPermissions::permissionName('publish', 'blog-posts'));

        config(['flex-fields.permissions.format' => 'flex.{entity}.{ability}']);
        $this->assertSame('flex.blog_posts.create', FlexPermissions::permissionName('create', $this->entity));

        config(['flex-fields.permissions.format' => '{ability}_flex_{entity}']);
        $all = FlexPermissions::all();
        $this->assertContains('manage_flex_structure', $all);
        $this->assertContains('force_delete_flex_blog_posts', $all);
        $this->assertCount(11, $all);
    }

    public function test_everything_is_allowed_while_disabled(): void
    {
        $this->assertTrue(FlexPermissions::allows(null, 'delete', $this->entity));
        $this->assertTrue(FlexPermissions::allowsStructure());
    }

    public function test_checks_go_through_the_gate_when_enabled(): void
    {
        config(['flex-fields.permissions.enabled' => true]);
        $user = $this->makeUser();

        $this->assertFalse(FlexPermissions::allows(null, 'view_any', $this->entity), 'Guests are denied.');
        $this->assertFalse(FlexPermissions::allows($user, 'view_any', $this->entity));

        $this->granted = ['view_any_flex_blog_posts'];
        $this->assertTrue(FlexPermissions::allows($user, 'view_any', $this->entity));
        $this->assertFalse(FlexPermissions::allows($user, 'delete', $this->entity));
        $this->assertFalse(FlexPermissions::allowsStructure($user));

        Gate::define('manage_flex_structure', fn () => true);
        $this->assertTrue(FlexPermissions::allowsStructure($user));
    }

    public function test_filament_resources_respect_permissions(): void
    {
        config(['flex-fields.permissions.enabled' => true]);
        // Skip the regular policies so only FlexFields permissions are evaluated.
        foreach ([EntityDataResource::class, EntityResource::class, CustomFieldResource::class] as $resource) {
            $resource::skipAuthorization();
        }
        $this->actingAs($this->makeUser());

        $record = (new RecordDomainService)->create('blog-posts', ['title' => 'Hello']);

        $this->assertFalse(EntityDataResource::getAuthorizationResponse('update', $record)->allowed());
        $this->assertFalse(EntityResource::getAuthorizationResponse('viewAny')->allowed());
        $this->assertFalse(CustomFieldResource::getAuthorizationResponse('create')->allowed());

        $this->granted = ['update_flex_blog_posts'];
        $this->assertTrue(EntityDataResource::getAuthorizationResponse('update', $record)->allowed());
        $this->assertFalse(EntityDataResource::getAuthorizationResponse('forceDelete', $record)->allowed());

        foreach ([EntityDataResource::class, EntityResource::class, CustomFieldResource::class] as $resource) {
            $resource::skipAuthorization(false);
        }
    }

    public function test_ai_tools_respect_permissions(): void
    {
        config(['flex-fields.permissions.enabled' => true]);
        $context = (new AiContext)->forUser($this->makeUser())->withoutApprovals();

        $schema = (new GetEntitySchemaTool)->setContext($context)->handle(new Request(['entity' => 'blog-posts']));
        $this->assertStringContainsString('view_any_flex_blog_posts', $schema);

        $list = json_decode((new ListEntitiesTool)->setContext($context)->handle(new Request([])), true);
        $this->assertSame(0, $list['count'] ?? count($list['entities'] ?? []));

        $field = (new CreateFieldTool)->setContext($context)->handle(new Request(['entity' => 'blog-posts', 'label' => 'X', 'type' => 'text']));
        $this->assertStringContainsString('manage_flex_structure', $field);

        $this->granted = ['view_any_flex_blog_posts', 'create_flex_blog_posts'];

        $this->assertStringContainsString('"slug": "blog-posts"', (new GetEntitySchemaTool)->setContext($context)->handle(new Request(['entity' => 'blog-posts'])));

        // Without publish permission new records are drafts and publishing is rejected.
        $created = json_decode((new CreateRecordTool)->setContext($context)->handle(new Request(['entity' => 'blog-posts', 'title' => 'Draft'])), true);
        $this->assertSame('draft', $created['record']['status']);

        $rejected = (new CreateRecordTool)->setContext($context)->handle(new Request(['entity' => 'blog-posts', 'title' => 'Live', 'status' => 'published']));
        $this->assertStringContainsString('publish_flex_blog_posts', $rejected);

        $publish = (new PublishRecordTool)->setContext($context)->handle(new Request(['entity' => 'blog-posts', 'record' => (string) $created['record']['id']]));
        $this->assertStringContainsString('publish_flex_blog_posts', $publish);
    }

    public function test_permissions_command_lists_permissions(): void
    {
        $this->artisan('flex:permissions')
            ->expectsOutputToContain('view_any_flex_blog_posts')
            ->assertSuccessful();

        $this->artisan('flex:permissions', ['--sync' => true])->assertFailed();
    }
}
