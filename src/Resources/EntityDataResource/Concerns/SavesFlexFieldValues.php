<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityDataResource\Concerns;

use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Services\RecordHistoryService;
use IvanMercedes\FlexFields\Support\RecordEvents;

/**
 * Persists the "ff_*" form state of a record through the domain layer, stores
 * a history revision and dispatches the record lifecycle events.
 */
trait SavesFlexFieldValues
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveCustomFieldValues(EntityRecord $record, array $data, string $event): void
    {
        $values = [];

        foreach ($record->entity->customFields()->get() as $field) {
            $values[$field->key] = $data['ff_' . $field->key] ?? null;
        }

        $previousStatus = $record->wasChanged('status') ? ($record->getPrevious()['status'] ?? null) : $record->status;

        app(RecordDomainService::class)->writeValues($record, $values);
        app(RecordHistoryService::class)->record($record->refresh(), $event);

        if ($event === RecordHistoryService::EVENT_CREATED) {
            RecordEvents::created($record);
        } else {
            RecordEvents::updated($record, $previousStatus);
        }
    }
}
