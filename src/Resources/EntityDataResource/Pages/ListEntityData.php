<?php

namespace IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use IvanMercedes\FlexFields\Resources\EntityDataResource;

class ListEntityData extends ListRecords
{
    protected static string $resource = EntityDataResource::class;

    protected function getHeaderActions(): array
    {
        $entity = EntityDataResource::getCurrentEntity();

        return [
            Actions\CreateAction::make()
                ->url(fn () => EntityDataResource::getUrl('create', [
                    'entity' => $entity?->id,
                ])),
        ];
    }
}
