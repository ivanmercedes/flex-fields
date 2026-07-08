<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use IvanMercedes\FlexFields\FlexFieldsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            FlexFieldsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        /*
        $migration = include __DIR__.'/../database/migrations/create_flex_fields_table.php.stub';
        $migration->up();
        */
    }
}
