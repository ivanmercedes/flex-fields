<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields;

use Filament\Contracts\Plugin;
use Filament\Panel;
use IvanMercedes\FlexFields\Filament\Pages\FlexFieldsDashboard;
use IvanMercedes\FlexFields\Filament\Widgets\EntitiesOverviewWidget;
use IvanMercedes\FlexFields\Resources\CustomFieldResource;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Resources\EntityResource;

class FlexFieldsPlugin implements Plugin
{
    protected bool $showDashboardPage = true;

    protected bool $showOverviewWidget = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'flex-fields';
    }

    public function register(Panel $panel): void
    {
        $resources = [
            EntityResource::class,
            CustomFieldResource::class,
            EntityDataResource::class,
        ];

        $pages = [];
        $widgets = [];

        if ($this->showDashboardPage) {
            $pages[] = FlexFieldsDashboard::class;
        }

        if ($this->showOverviewWidget) {
            $widgets[] = EntitiesOverviewWidget::class;
        }

        $panel
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function showDashboardPage(bool $show = true): static
    {
        $this->showDashboardPage = $show;

        return $this;
    }

    public function showOverviewWidget(bool $show = true): static
    {
        $this->showOverviewWidget = $show;

        return $this;
    }
}
