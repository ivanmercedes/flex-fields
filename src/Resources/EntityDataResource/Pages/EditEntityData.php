<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Concerns\SavesFlexFieldValues;
use IvanMercedes\FlexFields\Services\RecordHistoryService;

class EditEntityData extends EditRecord
{
    use SavesFlexFieldValues;

    protected static string $resource = EntityDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;
        $record->load('fieldValues.customField');

        foreach ($record->fieldValues as $fv) {
            $key = 'ff_' . optional($fv->customField)->key;

            if ($key && $fv->customField) {
                $data[$key] = $fv->getCastedValue();
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->saveCustomFieldValues($this->record, $this->data, RecordHistoryService::EVENT_UPDATED);
    }
}
