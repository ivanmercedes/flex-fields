<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeSchemaCommand extends Command
{
    protected $signature = 'flex:make-schema {name : The name of the schema/entity}';

    protected $description = 'Create a new FlexFields schema class';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        $fileName = date('Y_m_d_His') . '_create_' . Str::snake($name) . '_schema.php';

        $path = database_path('flex-schemas');

        if (! $this->files->exists($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

        $stub = $this->files->get(__DIR__ . '/stubs/schema.stub');

        $stub = str_replace(
            ['DummyName', 'dummy-slug'],
            [$name, Str::slug($name)],
            $stub
        );

        $this->files->put($path . '/' . $fileName, $stub);

        $this->info("FlexFields schema [{$fileName}] created successfully.");

        return self::SUCCESS;
    }
}
