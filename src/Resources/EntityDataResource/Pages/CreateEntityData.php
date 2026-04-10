<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Models\FieldValue;
use IvanMercedes\FlexFields\Resources\EntityDataResource;

class CreateEntityData extends CreateRecord
{
    protected static string $resource = EntityDataResource::class;

    protected function afterCreate(): void
    {
        $this->saveCustomFieldValues($this->record, $this->data);
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
