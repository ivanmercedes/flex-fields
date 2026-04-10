<?php

namespace IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;

class EditCustomField extends EditRecord
{
    protected static string $resource = CustomFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
