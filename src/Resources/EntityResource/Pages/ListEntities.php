<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Resources\EntityResource;

class ListEntities extends ListRecords
{
    public array $entities = [];

    protected static string $resource = EntityResource::class;

    protected string $view = 'flex-fields::filament.pages.list-entities';

    public function mount(): void
    {
        parent::mount();

        $this->loadEntities();
    }

    public function loadEntities(): void
    {
        $this->entities = Entity::withCount(['customFields', 'records'])
            ->orderBy('menu_order')
            ->get()
            ->toArray();
    }

    public function getEntityDataUrl(int $entityId): string
    {
        return EntityDataResource::getUrl('index', ['entity' => $entityId]);
    }

    public function getEditEntityUrl(int $entityId): string
    {
        return EntityResource::getUrl('edit', ['record' => $entityId]);
    }

    public function getManageFieldsUrl(int $entityId): string
    {
        return CustomFieldResource::getUrl('index', [
            'tableFilters[entity_id][value]' => $entityId,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
