<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;
use IvanMercedes\FlexFields\Resources\CustomFieldResource\Concerns\PreparesFieldData;

class EditCustomField extends EditRecord
{
    use PreparesFieldData;

    protected static string $resource = CustomFieldResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillBuilders($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->applyBuilders($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
