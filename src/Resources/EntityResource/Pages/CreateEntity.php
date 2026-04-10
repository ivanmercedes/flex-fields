<?php

namespace IvanMercedes\FlexFields\Resources\EntityResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use IvanMercedes\FlexFields\Resources\EntityResource;

class CreateEntity extends CreateRecord
{
    protected static string $resource = EntityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
