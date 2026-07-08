<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityCategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use IvanMercedes\FlexFields\Resources\EntityCategoryResource;

class CreateEntityCategory extends CreateRecord
{
    protected static string $resource = EntityCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['entity' => $this->record->entity_id]);
    }
}
