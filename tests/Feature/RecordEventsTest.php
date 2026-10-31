<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use Illuminate\Support\Facades\Event;
use IvanMercedes\FlexFields\Events\EntitySchemaChanged;
use IvanMercedes\FlexFields\Events\RecordCreated;
use IvanMercedes\FlexFields\Events\RecordDeleted;
use IvanMercedes\FlexFields\Events\RecordPublished;
use IvanMercedes\FlexFields\Events\RecordRestored;
use IvanMercedes\FlexFields\Events\RecordUnpublished;
use IvanMercedes\FlexFields\Events\RecordUpdated;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Services\RecordHistoryService;
use IvanMercedes\FlexFields\Tests\TestCase;

class RecordEventsTest extends TestCase
{
    protected RecordDomainService $records;

    protected function setUp(): void
    {
        parent::setUp();

        $entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $entity->customFields()->create(['label' => 'Price', 'key' => 'price', 'type' => 'number']);

        $this->records = new RecordDomainService;
    }

    public function test_creating_a_published_record_dispatches_created_and_published(): void
    {
        Event::fake([RecordCreated::class, RecordPublished::class]);

        $record = $this->records->create('product', ['title' => 'Phone', 'status' => 'published'], ['price' => 10]);

        Event::assertDispatched(RecordCreated::class, fn (RecordCreated $event) => $event->recordId === $record->id
            && $event->entitySlug === 'product'
            && $event->record->getValue('price') == 10);
        Event::assertDispatched(RecordPublished::class);
    }

    public function test_draft_records_are_not_published(): void
    {
        Event::fake([RecordCreated::class, RecordPublished::class]);

        $this->records->create('product', ['title' => 'Phone', 'status' => 'draft']);

        Event::assertDispatched(RecordCreated::class);
        Event::assertNotDispatched(RecordPublished::class);
    }

    public function test_status_transitions_dispatch_publishing_events(): void
    {
        $record = $this->records->create('product', ['title' => 'Phone', 'status' => 'draft']);

        Event::fake([RecordUpdated::class, RecordPublished::class, RecordUnpublished::class]);

        $this->records->update('product', $record->id, ['title' => 'Phone Pro']);
        Event::assertDispatchedTimes(RecordUpdated::class, 1);
        Event::assertNotDispatched(RecordPublished::class);

        $this->records->publish('product', $record->id);
        Event::assertDispatchedTimes(RecordPublished::class, 1);

        $this->records->update('product', $record->id, ['status' => 'archived']);
        Event::assertDispatchedTimes(RecordUnpublished::class, 1);
    }

    public function test_reverting_a_revision_dispatches_updated_and_publishing_events(): void
    {
        $record = $this->records->create('product', ['title' => 'Phone', 'status' => 'published']);
        $this->records->unpublish('product', $record->id);

        Event::fake([RecordUpdated::class, RecordPublished::class]);

        $history = app(RecordHistoryService::class);
        $history->revert($history->revisions($record)->last());

        Event::assertDispatched(RecordUpdated::class);
        Event::assertDispatched(RecordPublished::class);
    }

    public function test_delete_restore_and_force_delete_dispatch_events(): void
    {
        $record = $this->records->create('product', ['title' => 'Phone']);

        Event::fake([RecordDeleted::class, RecordRestored::class]);

        $this->records->delete('product', $record->id);
        Event::assertDispatched(RecordDeleted::class, fn (RecordDeleted $event) => ! $event->force);

        $this->records->restore('product', $record->id);
        Event::assertDispatched(RecordRestored::class);

        $this->records->delete('product', $record->id, force: true);
        Event::assertDispatched(RecordDeleted::class, fn (RecordDeleted $event) => $event->force
            && $event->recordId === $record->id
            && $event->entitySlug === 'product');
        Event::assertDispatchedTimes(RecordDeleted::class, 2);
    }

    public function test_structure_changes_dispatch_schema_changed(): void
    {
        Event::fake([EntitySchemaChanged::class]);

        $fields = new FieldDomainService;
        $fields->create('product', ['label' => 'Color', 'type' => 'color']);
        $fields->delete('product', 'color');
        Entity::where('slug', 'product')->first()->delete();

        Event::assertDispatched(EntitySchemaChanged::class, fn (EntitySchemaChanged $event) => $event->entitySlug === 'product' && ! $event->deleted);
        Event::assertDispatched(EntitySchemaChanged::class, fn (EntitySchemaChanged $event) => $event->deleted);
    }
}
