<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;
use IvanMercedes\FlexFields\Resources\CustomFieldResource\Concerns\PreparesFieldData;

class CreateCustomField extends CreateRecord
{
    use PreparesFieldData;

    protected static string $resource = CustomFieldResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->applyBuilders($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
