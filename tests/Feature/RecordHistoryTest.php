<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\RecordRevision;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Services\RecordHistoryService;
use IvanMercedes\FlexFields\Tests\TestCase;

class RecordHistoryTest extends TestCase
{
    protected Entity $entity;

    protected RecordDomainService $records;

    protected RecordHistoryService $history;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->entity->customFields()->create(['label' => 'Price', 'key' => 'price', 'type' => 'number']);
        $this->entity->customFields()->create(['label' => 'Tags', 'key' => 'tags', 'type' => 'tags']);

        $this->records = new RecordDomainService;
        $this->history = app(RecordHistoryService::class);
    }

    public function test_create_and_update_store_revisions_with_diff_and_user(): void
    {
        $user = $this->makeUser('Ada Lovelace');
        $this->actingAs($user);

        $record = $this->records->create('product', ['title' => 'Phone'], ['price' => 100, 'tags' => ['a']]);
        $this->records->update('product', $record->id, ['title' => 'Phone Pro'], ['price' => 150]);

        $revisions = $this->history->revisions($record);

        $this->assertCount(2, $revisions);
        $this->assertSame('updated', $revisions[0]->event);
        $this->assertSame('created', $revisions[1]->event);
        $this->assertSame('Ada Lovelace', $revisions[0]->user_name);
        $this->assertSame($user->id, $revisions[0]->user_id);

        $this->assertEqualsCanonicalizing(['title', 'fields.price'], $revisions[0]->changedKeys());
        $this->assertEquals(['old' => 100, 'new' => 150], $revisions[0]->diff['fields.price']);
        $this->assertSame('Phone Pro', $revisions[0]->snapshot['title']);
        $this->assertEquals(['a'], $revisions[0]->snapshot['fields']['tags']);
    }

    public function test_no_op_updates_are_not_recorded(): void
    {
        $record = $this->records->create('product', ['title' => 'Phone'], ['price' => 100]);
        $this->records->update('product', $record->id, ['title' => 'Phone'], ['price' => 100]);

        $this->assertCount(1, $this->history->revisions($record));
    }

    public function test_delete_and_restore_events_are_recorded(): void
    {
        $record = $this->records->create('product', ['title' => 'Phone']);

        $this->records->delete('product', $record->id);
        $this->records->restore('product', $record->id);

        $events = $this->history->revisions($record)->pluck('event')->all();
        $this->assertSame(['restored', 'deleted', 'created'], $events);
    }

    public function test_force_delete_removes_revisions(): void
    {
        $record = $this->records->create('product', ['title' => 'Phone']);
        $this->records->delete('product', $record->id, force: true);

        $this->assertSame(0, RecordRevision::count());
    }

    public function test_revert_restores_previous_version_and_logs_it(): void
    {
        $category = $this->entity->categories()->create(['name' => 'Phones', 'slug' => 'phones']);

        $record = $this->records->create('product', ['title' => 'Phone', 'status' => 'draft'], ['price' => 100, 'tags' => ['x']], [$category->id]);
        $this->records->update('product', $record->id, ['title' => 'Changed', 'status' => 'published'], ['price' => 999, 'tags' => []], []);

        $original = $this->history->revisions($record)->last();
        $restored = $this->history->revert($original);

        $this->assertSame('Phone', $restored->title);
        $this->assertSame('draft', $restored->status);
        $this->assertEquals(100, $restored->getValue('price'));
        $this->assertEquals(['x'], $restored->getValue('tags'));
        $this->assertSame([$category->id], $restored->categories->pluck('id')->all());
        $this->assertSame('reverted', $this->history->revisions($record)->first()->event);
    }

    public function test_revert_restores_trashed_records(): void
    {
        $record = $this->records->create('product', ['title' => 'Phone']);
        $created = $this->history->revisions($record)->first();
        $this->records->delete('product', $record->id);

        $restored = $this->history->revert($created);

        $this->assertFalse($restored->trashed());
    }

    public function test_revisions_are_pruned_to_max(): void
    {
        config(['flex-fields.history.max_revisions' => 3]);

        $record = $this->records->create('product', ['title' => 'Phone']);
        foreach ([1, 2, 3, 4] as $price) {
            $this->records->setValues('product', $record->id, ['price' => $price]);
        }

        $revisions = $this->history->revisions($record);
        $this->assertCount(3, $revisions);
        $this->assertEquals(4, $revisions->first()->snapshot['fields']['price']);
    }

    public function test_history_can_be_disabled(): void
    {
        config(['flex-fields.history.enabled' => false]);

        $record = $this->records->create('product', ['title' => 'Phone']);
        $this->records->update('product', $record->id, ['title' => 'Other']);

        $this->assertSame(0, RecordRevision::count());
        $this->assertNull($this->history->record($record, 'updated'));
    }

    public function test_format_for_api_and_ai(): void
    {
        $record = $this->records->create('product', ['title' => 'Phone'], ['price' => 1]);
        $formatted = $this->history->format($this->history->revisions($record)->first(), withSnapshot: true);

        $this->assertSame('created', $formatted['event']);
        $this->assertContains('fields.price', $formatted['changed']);
        $this->assertSame('Phone', $formatted['snapshot']['title']);
    }
}
