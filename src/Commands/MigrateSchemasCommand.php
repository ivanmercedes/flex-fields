<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class MigrateSchemasCommand extends Command
{
    protected $signature = 'flex:migrate';

    protected $description = 'Run pending FlexFields schemas';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $path = database_path('flex-schemas');

        if (! $this->files->exists($path)) {
            $this->info('No schemas found.');

            return self::SUCCESS;
        }

        $schemaFiles = $this->files->files($path);

        if (empty($schemaFiles)) {
            $this->info('No pending schemas.');

            return self::SUCCESS;
        }

        // Ensure the table exists
        if (! DB::getSchemaBuilder()->hasTable('ff_schemas')) {
            $this->error('The ff_schemas table does not exist. Please run standard migrations first.');

            return self::FAILURE;
        }

        $ranSchemas = DB::table('ff_schemas')->pluck('schema')->toArray();
        $pendingSchemas = [];

        foreach ($schemaFiles as $file) {
            $fileName = $file->getFilename();
            if (! in_array($fileName, $ranSchemas)) {
                $pendingSchemas[] = $file;
            }
        }

        if (empty($pendingSchemas)) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        $batch = DB::table('ff_schemas')->max('batch') ?? 0;
        $batch++;

        foreach ($pendingSchemas as $file) {
            $fileName = $file->getFilename();
            $this->components->task("Migrating: {$fileName}", function () use ($file, $fileName, $batch) {
                $schema = require $file->getPathname();
                $schema->up();

                DB::table('ff_schemas')->insert([
                    'schema' => $fileName,
                    'batch' => $batch,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }

        $this->newLine();
        $this->info('FlexFields schemas migrated successfully.');

        return self::SUCCESS;
    }
}
