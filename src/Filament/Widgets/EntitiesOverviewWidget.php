<?php

namespace IvanMercedes\FlexFields\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Support\Label;

class EntitiesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        $entityCount = Entity::count();
        $fieldCount = CustomField::count();
        $recordCount = EntityRecord::count();
        $activeCount = Entity::where('is_active', true)->count();

        return [
            Stat::make(Label::trans('flex-fields::flex-fields.dashboard.stats.entities'), $entityCount)
                ->description(Label::trans('flex-fields::flex-fields.dashboard.stats.active', ['count' => $activeCount]))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary')
                ->icon('heroicon-o-cube'),

            Stat::make(Label::trans('flex-fields::flex-fields.dashboard.stats.custom_fields'), $fieldCount)
                ->description(Label::trans('flex-fields::flex-fields.dashboard.stats.across_all_entities'))
                ->descriptionIcon('heroicon-m-variable')
                ->color('info')
                ->icon('heroicon-o-variable'),

            Stat::make(Label::trans('flex-fields::flex-fields.dashboard.stats.total_records'), $recordCount)
                ->description(Label::trans('flex-fields::flex-fields.dashboard.stats.all_entity_entries'))
                ->descriptionIcon('heroicon-m-table-cells')
                ->color('success')
                ->icon('heroicon-o-table-cells'),
        ];
    }
}
