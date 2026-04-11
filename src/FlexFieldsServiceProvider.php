<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields;

// use Filament\Support\Facades\FilamentView;
use Filament\Support\Assets\Css;
// use Filament\View\PanelsRenderHook;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use IvanMercedes\FlexFields\Commands\InstallFlexFieldsCommand;

class FlexFieldsServiceProvider extends ServiceProvider
{
    public static string $name = 'flex-fields';

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/flex-fields.php',
            'flex-fields'
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'flex-fields');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'flex-fields');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        FilamentAsset::register([
            Css::make('flex-fields', __DIR__ . '/../resources/dist/flex-fields.css'),
        ], package: 'ivanmercedes/flex-fields');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'flex-fields-migrations');

            $this->publishes([
                __DIR__ . '/../config/flex-fields.php' => config_path('flex-fields.php'),
            ], 'flex-fields-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/flex-fields'),
            ], 'flex-fields-views');

            $this->commands([
                InstallFlexFieldsCommand::class,
            ]);
        }
    }
}
