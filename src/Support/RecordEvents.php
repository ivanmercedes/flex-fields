<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use IvanMercedes\FlexFields\Events\RecordCreated;
use IvanMercedes\FlexFields\Events\RecordPublished;
use IvanMercedes\FlexFields\Events\RecordUnpublished;
use IvanMercedes\FlexFields\Events\RecordUpdated;
use IvanMercedes\FlexFields\Models\EntityRecord;

/**
 * Dispatches the record lifecycle events once a write (attributes, categories
 * and field values) is complete, plus the publishing events derived from the
 * status transition.
 *
 * Deletions and restores are dispatched by the record model itself.
 */
class RecordEvents
{
    public const PUBLISHED = 'published';

    public static function created(EntityRecord $record): void
    {
        RecordCreated::dispatch($record);

        if ($record->status === self::PUBLISHED) {
            RecordPublished::dispatch($record);
        }
    }

    public static function updated(EntityRecord $record, ?string $previousStatus): void
    {
        RecordUpdated::dispatch($record);

        if ($previousStatus === null || $previousStatus === $record->status) {
            return;
        }

        if ($record->status === self::PUBLISHED) {
            RecordPublished::dispatch($record);
        } elseif ($previousStatus === self::PUBLISHED) {
            RecordUnpublished::dispatch($record);
        }
    }
}
