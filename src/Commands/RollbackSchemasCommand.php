<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class RollbackSchemasCommand extends Command
{
    protected $signature = 'flex:rollback';

    protected $description = 'Rollback the last batch of FlexFields schemas';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('ff_schemas')) {
            $this->error('The ff_schemas table does not exist.');

            return self::FAILURE;
        }

        $lastBatch = DB::table('ff_schemas')->max('batch');

        if (! $lastBatch) {
            $this->info('Nothing to rollback.');

            return self::SUCCESS;
        }

        $schemasToRollback = DB::table('ff_schemas')
            ->where('batch', $lastBatch)
            ->orderBy('id', 'desc')
            ->get();

        $path = database_path('flex-schemas');

        foreach ($schemasToRollback as $schemaRow) {
            $fileName = $schemaRow->schema;
            $filePath = $path . '/' . $fileName;

            $this->components->task("Rolling back: {$fileName}", function () use ($filePath, $schemaRow) {
                if ($this->files->exists($filePath)) {
                    $schema = require $filePath;
                    $schema->down();
                } else {
                    $this->warn(" Schema file missing: {$filePath}. Record will still be removed.");
                }

                DB::table('ff_schemas')->where('id', $schemaRow->id)->delete();
            });
        }

        $this->newLine();
        $this->info('FlexFields schemas rolled back successfully.');

        return self::SUCCESS;
    }
}
