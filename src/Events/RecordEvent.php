<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Events;

use Illuminate\Foundation\Events\Dispatchable;
use IvanMercedes\FlexFields\Models\EntityRecord;

/**
 * Base class of the record lifecycle events. Besides the record, it keeps the
 * identifiers listeners usually need (webhooks, cache tags, search indexes),
 * so they keep working once the record no longer exists.
 */
abstract class RecordEvent
{
    use Dispatchable;

    public readonly int $recordId;

    public readonly ?string $recordSlug;

    public readonly int $entityId;

    public readonly ?string $entitySlug;

    public readonly ?int $tenantId;

    public function __construct(public readonly EntityRecord $record)
    {
        $this->recordId = (int) $record->getKey();
        $this->recordSlug = $record->slug;
        $this->entityId = (int) $record->entity_id;
        $this->entitySlug = $record->entity?->slug;
        $this->tenantId = $record->tenant_id !== null ? (int) $record->tenant_id : null;
    }
}
