<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use IvanMercedes\FlexFields\Http\Resources\RecordResource;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Tests\Fixtures\User;
use IvanMercedes\FlexFields\Tests\TestCase;

/**
 * How records are presented by the REST API: media URLs, locales, sparse
 * fields, expanded relations and application extensions.
 */
class RestApiContentTest extends TestCase
{
    protected RecordDomainService $records;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $author = Entity::create(['name' => 'Author', 'slug' => 'author']);
        $author->customFields()->create(['label' => 'Avatar', 'key' => 'avatar', 'type' => 'image']);

        $post = Entity::create(['name' => 'Post', 'slug' => 'post']);
        $post->customFields()->create(['label' => 'Headline', 'key' => 'headline', 'type' => 'text', 'settings' => ['translatable' => true]]);
        $post->customFields()->create(['label' => 'Cover', 'key' => 'cover', 'type' => 'image']);
        $post->customFields()->create(['label' => 'Gallery', 'key' => 'gallery', 'type' => 'image', 'settings' => ['multiple' => true]]);
        $post->customFields()->create(['label' => 'Price', 'key' => 'price', 'type' => 'number']);
        $post->customFields()->create(['label' => 'Author', 'key' => 'author', 'type' => 'relationship', 'settings' => ['related_entity' => 'author']]);
        $post->customFields()->create(['label' => 'Slides', 'key' => 'slides', 'type' => 'repeater', 'settings' => ['schema' => [
            ['key' => 'caption', 'label' => 'Caption', 'type' => 'text'],
            ['key' => 'photo', 'label' => 'Photo', 'type' => 'image'],
        ]]]);

        $this->records = new RecordDomainService;
        $this->user = $this->makeUser();
    }

    protected function tearDown(): void
    {
        RecordResource::flushExtensions();

        parent::tearDown();
    }

    public function test_media_urls_mirror_the_stored_paths(): void
    {
        $this->records->create('post', ['title' => 'Hello'], [
            'cover' => 'posts/cover.jpg',
            'gallery' => ['posts/a.jpg', 'https://cdn.example.com/b.jpg'],
            'slides' => [['caption' => 'One', 'photo' => 'posts/one.jpg']],
            'price' => 5,
        ]);

        $this->actingAs($this->user)->getJson('/api/flex/post/hello')
            ->assertOk()
            ->assertJsonPath('data.values.cover', 'posts/cover.jpg')
            ->assertJsonPath('data.media.cover', Storage::disk('public')->url('posts/cover.jpg'))
            ->assertJsonPath('data.media.gallery', [Storage::disk('public')->url('posts/a.jpg'), 'https://cdn.example.com/b.jpg'])
            ->assertJsonPath('data.media.slides.0.photo', Storage::disk('public')->url('posts/one.jpg'))
            ->assertJsonMissingPath('data.media.price');
    }

    public function test_records_without_media_expose_an_empty_object(): void
    {
        $this->records->create('post', ['title' => 'Plain'], ['price' => 5]);

        $response = $this->actingAs($this->user)->getJson('/api/flex/post/plain')->assertOk();

        $this->assertStringContainsString('"media":{}', $response->getContent());
    }

    public function test_locale_resolves_translatable_values_with_fallback(): void
    {
        config()->set('flex-fields.translations.locales', ['en', 'es', 'fr']);

        $this->records->create('post', ['title' => 'Hello'], ['headline' => ['en' => 'Hello', 'es' => 'Hola']]);

        $this->actingAs($this->user)->getJson('/api/flex/post/hello')
            ->assertJsonPath('data.values.headline', ['en' => 'Hello', 'es' => 'Hola']);

        $this->actingAs($this->user)->getJson('/api/flex/post/hello?locale=es')
            ->assertJsonPath('data.values.headline', 'Hola');

        $this->actingAs($this->user)->getJson('/api/flex/post/hello?locale=fr')
            ->assertJsonPath('data.values.headline', 'Hello');

        $this->actingAs($this->user)->getJson('/api/flex/post/hello?locale=de')
            ->assertUnprocessable();
    }

    public function test_fields_limits_the_returned_values(): void
    {
        $this->records->create('post', ['title' => 'Hello'], ['price' => 5, 'cover' => 'posts/cover.jpg', 'headline' => ['en' => 'Hi']]);

        $this->actingAs($this->user)->getJson('/api/flex/post?fields=price,cover')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Hello')
            ->assertJsonPath('data.0.values', ['cover' => 'posts/cover.jpg', 'price' => 5])
            ->assertJsonPath('data.0.media', ['cover' => Storage::disk('public')->url('posts/cover.jpg')]);
    }

    public function test_relations_can_include_the_related_values(): void
    {
        $author = $this->records->create('author', ['title' => 'Ada'], ['avatar' => 'authors/ada.jpg']);
        $this->records->create('post', ['title' => 'Hello'], ['author' => $author->id]);

        $this->actingAs($this->user)->getJson('/api/flex/post/hello?include=relations')
            ->assertJsonPath('data.relations.author.0.title', 'Ada')
            ->assertJsonMissingPath('data.relations.author.0.values');

        $this->actingAs($this->user)->getJson('/api/flex/post/hello?include=relations.values')
            ->assertJsonPath('data.relations.author.0.values.avatar', 'authors/ada.jpg')
            ->assertJsonPath('data.relations.author.0.media.avatar', Storage::disk('public')->url('authors/ada.jpg'));
    }

    public function test_applications_can_extend_the_record_representation(): void
    {
        RecordResource::extend(fn (array $data, EntityRecord $record, Request $request) => $data + [
            'url' => "https://example.com/{$record->entity->slug}/{$record->slug}",
        ]);

        $this->records->create('post', ['title' => 'Hello']);

        $this->actingAs($this->user)->getJson('/api/flex/post/hello')
            ->assertJsonPath('data.url', 'https://example.com/post/hello');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('flex-fields.api.enabled', true);
        $app['config']->set('flex-fields.api.middleware', ['api', 'auth']);
    }
}
