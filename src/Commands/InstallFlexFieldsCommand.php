<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallFlexFieldsCommand extends Command
{
    protected $signature = 'flex-fields:install {--force : Overwrite existing files}';

    protected $description = 'Install the FlexFields plugin — custom entities & fields for Filament';

    public function handle(): int
    {
        $this->info('🚀 Installing FlexFields plugin...');
        $this->newLine();

        // 1. Publish config
        $this->callSilently('vendor:publish', [
            '--tag' => 'flex-fields-config',
            '--force' => $this->option('force'),
        ]);
        $this->line('Config published → config/flex-fields.php');

        // 2. Copy migrations
        $this->publishMigrations();

        // 3. Views (optional)
        if ($this->confirm('Would you like to publish views for customization?', false)) {
            $this->callSilently('vendor:publish', [
                '--tag' => 'flex-fields-views',
                '--force' => $this->option('force'),
            ]);
            $this->line('Views published → resources/views/vendor/flex-fields');
        }

        $this->newLine();
        $this->info('FlexFields installed successfully!');
        $this->newLine();
        $this->line('  Next steps:');
        $this->line('  1. Add <comment>FlexFieldsPlugin::make()</comment> to your Panel Provider');
        $this->line('  2. Run <comment>php artisan migrate</comment>');
        $this->line('  3. Visit /admin/entities to create your first Entity');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function publishMigrations(): void
    {
        $source = __DIR__ . '/../../database/migrations';
        $destination = database_path('migrations');

        if (! File::isDirectory($source)) {
            return;
        }

        $files = File::files($source);
        $copied = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $target = $destination . '/' . $file->getFilename();

            if (File::exists($target) && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            File::copy($file->getPathname(), $target);
            $copied++;
        }

        if ($copied > 0) {
            $this->line("  ✅ {$copied} migration(s) copied → database/migrations/");
        }
        if ($skipped > 0) {
            $this->line("  ⚠️  {$skipped} migration(s) skipped (use --force to overwrite)");
        }
    }
}
