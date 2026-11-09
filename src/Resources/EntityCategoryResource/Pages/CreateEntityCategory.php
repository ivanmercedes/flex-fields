<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityCategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use IvanMercedes\FlexFields\Resources\EntityCategoryResource;

class CreateEntityCategory extends CreateRecord
{
    protected static string $resource = EntityCategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['entity_id'] = EntityCategoryResource::getCurrentEntity()?->getKey();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['entity' => $this->record->entity_id]);
    }
}
