<?php

namespace IvanMercedes\FlexFields;

// use Filament\Support\Facades\FilamentView;
use Filament\Support\Facades\FilamentAsset;
// use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Assets\Css;

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

        // if (class_exists(FilamentView::class) && class_exists(PanelsRenderHook::class)) {
        //     $stylesPath = __DIR__ . '/../resources/dist/flex-fields.css';

        //     FilamentView::registerRenderHook(
        //         PanelsRenderHook::HEAD_END,
        //         fn(): string => is_file($stylesPath)
        //         ? '<style data-flex-fields-dashboard>' . file_get_contents($stylesPath) . '</style>'
        //         : '',
        //     );
        // }

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
                \IvanMercedes\FlexFields\Commands\InstallFlexFieldsCommand::class,
            ]);
        }
    }
}
