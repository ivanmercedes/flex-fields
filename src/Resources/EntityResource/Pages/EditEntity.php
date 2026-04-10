<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use IvanMercedes\FlexFields\Resources\EntityResource;

class EditEntity extends EditRecord
{
    protected static string $resource = EntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
