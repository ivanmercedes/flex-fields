<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\CategoryDomainService;
use IvanMercedes\FlexFields\Services\EntityDomainService;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\FieldGroupDomainService;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Services\SchemaTransfer;
use IvanMercedes\FlexFields\Tests\TestCase;

class SchemaTransferTest extends TestCase
{
    protected SchemaTransfer $transfer;

    protected RecordDomainService $records;

    protected function setUp(): void
    {
        parent::setUp();

        config(['flex-fields.tenancy.enabled' => true]);

        $entities = new EntityDomainService;
        $fields = new FieldDomainService($entities);
        $categories = new CategoryDomainService($entities);
        $this->records = new RecordDomainService($entities);
        $this->transfer = new SchemaTransfer($entities);

        $entities->create(['name' => 'Author', 'slug' => 'author'], 1);
        $fields->create('author', ['label' => 'Bio', 'type' => 'textarea'], 1);

        $entities->create(['name' => 'Post', 'slug' => 'post', 'icon' => 'heroicon-o-newspaper'], 1);
        $categories->create('post', ['name' => 'News', 'slug' => 'news'], 1);
        $categories->create('post', ['name' => 'Local', 'slug' => 'local', 'parent_id' => Entity::where('slug', 'post')->first()->categories()->where('slug', 'news')->value('id')], 1);
        (new FieldGroupDomainService($entities))->create('post', ['label' => 'Details', 'key' => 'details'], 1);
        $fields->create('post', ['label' => 'Has Video', 'key' => 'has_video', 'type' => 'boolean'], 1);
        $fields->create('post', ['label' => 'Video', 'key' => 'video', 'type' => 'url', 'group' => 'details', 'category_ids' => ['news'], 'settings' => ['show_when' => [['field' => 'has_video', 'operator' => 'is_true']]]], 1);
        $fields->create('post', ['label' => 'Authors', 'key' => 'authors', 'type' => 'relationship', 'settings' => ['related_entity' => 'author', 'multiple' => true]], 1);

        $ada = $this->records->create('author', ['title' => 'Ada'], ['bio' => 'Math'], tenantId: 1);
        $this->records->create('post', ['title' => 'Hello', 'status' => 'draft'], ['video' => 'https://v.test/1', 'authors' => [$ada->id]], ['local'], 1);
    }

    public function test_export_uses_slugs_and_keys_instead_of_ids(): void
    {
        $definition = $this->transfer->export(['post'], 1, withRecords: true);

        $post = $definition['entities'][0];

        $this->assertSame(SchemaTransfer::VERSION, $definition['version']);
        $this->assertCount(1, $definition['entities']);
        $this->assertSame('heroicon-o-newspaper', $post['icon']);
        $this->assertSame('news', collect($post['categories'])->firstWhere('slug', 'local')['parent']);
        $this->assertSame('details', $post['groups'][0]['key']);

        $video = collect($post['fields'])->firstWhere('key', 'video');
        $this->assertSame('details', $video['group']);
        $this->assertSame(['news'], $video['categories']);
        $this->assertSame('has_video', $video['settings']['show_when']['conditions'][0]['field']);

        $this->assertSame(['ada'], $post['records'][0]['values']['authors']);
        $this->assertSame(['local'], $post['records'][0]['categories']);
    }

    public function test_import_recreates_the_content_model_in_another_tenant(): void
    {
        $this->transfer->import($this->transfer->export(tenantId: 1, withRecords: true), 2);

        $post = (new EntityDomainService)->findOrFail('post', 2);
        $video = $post->customFields()->where('key', 'video')->first();
        $record = $this->records->findOrFail('post', 'hello', 2);
        $ada = $this->records->findOrFail('author', 'ada', 2);

        $this->assertSame(2, (int) $post->tenant_id);
        $this->assertSame('details', $video->fieldGroup->key);
        $this->assertSame([$post->categories()->where('slug', 'news')->value('id')], $video->category_ids);
        $this->assertSame('has_video', $video->settings['show_when']['conditions'][0]['field']);
        $this->assertSame('news', $post->categories()->where('slug', 'local')->first()->parent->slug);

        $this->assertSame('draft', $record->status);
        $this->assertSame([$ada->id], $record->getValue('authors'));
        $this->assertNotSame($ada->id, $this->records->findOrFail('author', 'ada', 1)->id);
    }

    public function test_importing_twice_updates_instead_of_duplicating(): void
    {
        $definition = $this->transfer->export(tenantId: 1, withRecords: true);
        $definition['entities'][1]['fields'][0]['label'] = 'Has a video';

        $this->transfer->import($definition, 2);
        $this->transfer->import($definition, 2);

        $post = (new EntityDomainService)->findOrFail('post', 2);

        $this->assertSame(3, $post->customFields()->count());
        $this->assertSame(1, $post->records()->count());
        $this->assertSame(2, $post->categories()->count());
        $this->assertSame('Has a video', $post->customFields()->where('key', 'has_video')->value('label'));
    }

    public function test_commands_round_trip_through_a_json_file(): void
    {
        $path = sys_get_temp_dir() . '/flex-schema-' . uniqid() . '.json';

        try {
            $this->artisan('flex:export', ['path' => $path, '--tenant' => 1, '--entity' => ['author']])->assertSuccessful();
            $this->artisan('flex:import', ['path' => $path, '--tenant' => 3])->expectsOutputToContain('Imported 1 entities.')->assertSuccessful();
        } finally {
            @unlink($path);
        }

        $this->assertNotNull((new EntityDomainService)->find('author', 3));
        $this->assertNull((new EntityDomainService)->find('post', 3));
    }
}
