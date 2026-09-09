<?php

namespace IvanMercedes\FlexFields\Tests;

use Filament\FilamentServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IvanMercedes\FlexFields\FlexFieldsServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            FilamentServiceProvider::class,
            AiServiceProvider::class,
            FlexFieldsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('flex-fields.tenancy.enabled', false);
        $app['config']->set('flex-fields.cache.enabled', false);
    }

    protected function setUpDatabase(): void
    {
        // Run all package migrations
        $migrationFiles = [
            __DIR__ . '/../database/migrations/2024_01_01_000001_create_ff_entities_table.php',
            __DIR__ . '/../database/migrations/2024_01_01_000002_create_ff_custom_fields_table.php',
            __DIR__ . '/../database/migrations/2024_01_01_000003_create_ff_records_and_values_tables.php',
            __DIR__ . '/../database/migrations/2024_01_01_000004_create_ff_categories_tables.php',
            __DIR__ . '/../database/migrations/2024_01_01_000005_create_ff_schemas_table.php',
            __DIR__ . '/../database/migrations/2024_01_01_000006_add_soft_deletes_to_ff_entity_records_table.php',
            __DIR__ . '/../database/migrations/2024_01_01_000007_add_category_ids_to_ff_custom_fields_table.php',
            __DIR__ . '/../database/migrations/2024_01_01_000008_add_is_shown_in_list_to_ff_custom_fields_table.php',
        ];

        foreach ($migrationFiles as $file) {
            $migration = require $file;
            $migration->up();
        }

        // Dummy teams table for multi-tenancy testing
        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->timestamps();
            });
        }
    }
}
