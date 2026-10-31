<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Http\FlexApi;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Support\RecordPreview;
use IvanMercedes\FlexFields\Tests\Fixtures\User;
use IvanMercedes\FlexFields\Tests\TestCase;

class RecordPreviewTest extends TestCase
{
    protected EntityRecord $draft;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $entity = Entity::create(['name' => 'Post', 'slug' => 'post']);
        $entity->customFields()->create(['label' => 'Body', 'key' => 'body', 'type' => 'textarea']);

        $this->draft = (new RecordDomainService)->create('post', ['title' => 'Draft', 'status' => 'draft'], ['body' => 'Soon']);
        $this->user = $this->makeUser();
    }

    protected function tearDown(): void
    {
        RecordPreview::urlUsing(null);
        FlexApi::resolveTenantUsing(null);

        parent::tearDown();
    }

    public function test_a_token_resolves_to_its_record_until_it_expires(): void
    {
        $token = RecordPreview::token($this->draft);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token, 'Tokens are URL safe.');
        $this->assertTrue($this->draft->is(RecordPreview::resolve($token)));

        $this->travel(61)->minutes();
        $this->assertNull(RecordPreview::resolve($token));
        $this->assertNull(RecordPreview::resolve('not-a-token'));
    }

    public function test_the_api_exchanges_a_token_for_the_draft(): void
    {
        $token = RecordPreview::token($this->draft);

        $this->actingAs($this->user)->getJson("/api/flex/_preview/{$token}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.values.body', 'Soon');

        $this->actingAs($this->user)->getJson('/api/flex/_preview/invalid')->assertNotFound();
    }

    public function test_tokens_of_another_tenant_are_rejected(): void
    {
        config(['flex-fields.tenancy.enabled' => true]);
        FlexApi::resolveTenantUsing(fn () => 99);

        $this->actingAs($this->user)->getJson('/api/flex/_preview/' . RecordPreview::token($this->draft))->assertNotFound();
    }

    public function test_preview_urls_come_from_the_pattern_or_a_resolver(): void
    {
        $this->assertNull(RecordPreview::url($this->draft));

        config(['flex-fields.preview.url' => 'https://site.test/{entity}/{slug}?preview={token}']);
        $this->assertStringStartsWith('https://site.test/post/draft?preview=', RecordPreview::url($this->draft));

        RecordPreview::urlUsing(fn (EntityRecord $record, string $token) => "https://custom.test/{$record->id}");
        $this->assertSame("https://custom.test/{$this->draft->id}", RecordPreview::url($this->draft));
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('flex-fields.api.enabled', true);
        $app['config']->set('flex-fields.api.middleware', ['api', 'auth']);
    }
}
