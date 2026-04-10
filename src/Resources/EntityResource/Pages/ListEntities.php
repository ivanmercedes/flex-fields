<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use IvanMercedes\FlexFields\Resources\EntityResource;

class ListEntities extends ListRecords
{
    protected static string $resource = EntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
