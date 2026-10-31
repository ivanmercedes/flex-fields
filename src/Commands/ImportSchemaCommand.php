<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use IvanMercedes\FlexFields\Services\SchemaTransfer;

class ImportSchemaCommand extends Command
{
    protected $signature = 'flex:import
        {path : JSON file exported with flex:export}
        {--tenant= : Tenant ID to import into}';

    protected $description = 'Create or update FlexFields content models (and records) from a JSON file';

    public function handle(SchemaTransfer $transfer): int
    {
        $path = (string) $this->argument('path');
        $definition = File::exists($path) ? json_decode(File::get($path), true) : null;

        if (! is_array($definition)) {
            $this->components->error("[{$path}] is not a readable JSON file.");

            return self::FAILURE;
        }

        $tenant = $this->option('tenant');
        $entities = $transfer->import($definition, $tenant === null ? null : (int) $tenant);

        $this->components->info('Imported ' . count($entities) . ' entities.');

        return self::SUCCESS;
    }
}
