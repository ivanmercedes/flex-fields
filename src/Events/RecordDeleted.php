<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Events;

use IvanMercedes\FlexFields\Models\EntityRecord;

/**
 * A record was deleted: soft-deleted, or permanently deleted when $force is true.
 */
class RecordDeleted extends RecordEvent
{
    public function __construct(EntityRecord $record, public readonly bool $force = false)
    {
        parent::__construct($record);
    }
}
