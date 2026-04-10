<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;

class ListCustomFields extends ListRecords
{
    protected static string $resource = CustomFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
