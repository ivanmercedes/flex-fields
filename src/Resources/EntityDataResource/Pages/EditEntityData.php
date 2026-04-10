<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Models\FieldValue;
use IvanMercedes\FlexFields\Resources\EntityDataResource;

class EditEntityData extends EditRecord
{
    protected static string $resource = EntityDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
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
        $this->saveCustomFieldValues($this->record, $this->data);
    }

    protected function saveCustomFieldValues(EntityRecord $record, array $data): void
    {
        $fields = CustomField::where('entity_id', $record->entity_id)->get();

        foreach ($fields as $field) {
            $key = 'ff_' . $field->key;
            $value = $data[$key] ?? null;

            if (is_array($value)) {
                $value = json_encode($value);
            }

            FieldValue::updateOrCreate(
                [
                    'entity_record_id' => $record->id,
                    'custom_field_id' => $field->id,
                ],
                ['value' => $value]
            );
        }
    }
}
