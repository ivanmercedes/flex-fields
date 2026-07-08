<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;
use IvanMercedes\FlexFields\Resources\EntityCategoryResource;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Resources\EntityResource;
use IvanMercedes\FlexFields\Support\Label;
use UnitEnum;

class FlexFieldsDashboard extends Page
{
    public array $stats = [];

    public array $entities = [];

    public int $totalFields = 0;

    public int $totalRecords = 0;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'flex-fields-dashboard';

    protected string $view = 'flex-fields::filament.pages.dashboard';

    public static function getNavigationGroup(): ?string
    {
        return Label::configOrTrans('flex-fields.navigation_group', 'flex-fields::flex-fields.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.navigation.dashboard');
    }

    public function mount(): void
    {
        $this->entities = Entity::withCount(['customFields', 'records'])->orderBy('menu_order')->get()->toArray();
        $this->totalFields = CustomField::count();
        $this->totalRecords = EntityRecord::count();
        $this->stats = [
            'entities' => Entity::count(),
            'fields' => $this->totalFields,
            'records' => $this->totalRecords,
            'active' => Entity::where('is_active', true)->count(),
        ];
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

    public function getManageCategoriesUrl(int $entityId): string
    {
        return EntityCategoryResource::getUrl('index', ['entity' => $entityId]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_entity')
                ->label(Label::trans('flex-fields::flex-fields.dashboard.actions.new_entity'))
                ->icon('heroicon-o-plus')
                ->url(EntityResource::getUrl('create')),

            Action::make('new_field')
                ->label(Label::trans('flex-fields::flex-fields.dashboard.actions.new_field'))
                ->icon('heroicon-o-variable')
                ->color('info')
                ->url(CustomFieldResource::getUrl('create')),
        ];
    }
}
