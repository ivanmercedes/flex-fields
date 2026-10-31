<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use IvanMercedes\FlexFields\Services\SchemaTransfer;

class ExportSchemaCommand extends Command
{
    protected $signature = 'flex:export
        {path : JSON file to write}
        {--entity=* : Only these entity slugs}
        {--tenant= : Tenant ID to export from}
        {--with-records : Include the records}';

    protected $description = 'Export FlexFields content models (and optionally records) to a JSON file';

    public function handle(SchemaTransfer $transfer): int
    {
        $tenant = $this->option('tenant');

        $definition = $transfer->export(
            $this->option('entity') ?: null,
            $tenant === null ? null : (int) $tenant,
            (bool) $this->option('with-records'),
        );

        File::put($this->argument('path'), json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

        $this->components->info('Exported ' . count($definition['entities']) . " entities to [{$this->argument('path')}].");

        return self::SUCCESS;
    }
}
