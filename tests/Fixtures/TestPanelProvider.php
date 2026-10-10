<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use IvanMercedes\FlexFields\FlexFieldsPlugin;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FlexFieldsPlugin::make());
    }
}
