<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Concerns\SavesFlexFieldValues;
use IvanMercedes\FlexFields\Services\RecordHistoryService;

class CreateEntityData extends CreateRecord
{
    use SavesFlexFieldValues;

    protected static string $resource = EntityDataResource::class;

    protected function afterCreate(): void
    {
        $this->saveCustomFieldValues($this->record, $this->data, RecordHistoryService::EVENT_CREATED);
    }

    protected function getRedirectUrl(): string
    {
        return EntityDataResource::getUrl('index', [
            'entity' => $this->record->entity_id,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['entity_id'])) {
            $data['entity_id'] = request()->get('entity');
        }

        return $data;
    }
}
