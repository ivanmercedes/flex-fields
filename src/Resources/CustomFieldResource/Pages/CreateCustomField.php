<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;

class CreateCustomField extends CreateRecord
{
    protected static string $resource = CustomFieldResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
