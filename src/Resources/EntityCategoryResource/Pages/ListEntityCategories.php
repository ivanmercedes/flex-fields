<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityCategoryResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use IvanMercedes\FlexFields\Resources\EntityCategoryResource;

class ListEntityCategories extends ListRecords
{
    protected static string $resource = EntityCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->url(fn (): string => EntityCategoryResource::getUrl('create', ['entity' => request('entity')])),
        ];
    }
}
