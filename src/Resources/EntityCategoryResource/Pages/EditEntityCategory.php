<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityCategoryResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use IvanMercedes\FlexFields\Resources\EntityCategoryResource;

class EditEntityCategory extends EditRecord
{
    protected static string $resource = EntityCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index', ['entity' => $this->record->entity_id]);
    }
}
