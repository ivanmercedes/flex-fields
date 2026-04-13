<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class InstallFlexFieldsCommand extends Command
{
    protected $signature = 'flex-fields:install {--force : Overwrite existing files}';

    protected $description = 'Install the FlexFields plugin — custom entities & fields for Filament';

    public function handle(): int
    {
        intro('Installing FlexFields Plugin...');

        // 1. Publish config
        spin(
            fn () => $this->callSilently('vendor:publish', [
                '--tag' => 'flex-fields-config',
                '--force' => $this->option('force'),
            ]),
            'Publishing configuration...'
        );
        info('Config published → config/flex-fields.php');

        // 2. Copy migrations
        $this->publishMigrations();

        // 3. Publish Filament Assets (CSS/JS)
        spin(
            fn () => $this->callSilently('filament:assets'),
            'Publishing Filament assets...'
        );
        info('Assets published → public/');

        outro('FlexFields installed successfully!');

        note("Next steps:\n 1. Add FlexFieldsPlugin::make() to your Panel Provider\n 2. Run `php artisan migrate`\n 3. Visit /admin/entities to create your first Entity");

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

        spin(
            function () use ($files, $destination, &$copied, &$skipped) {
                foreach ($files as $file) {
                    $target = $destination . '/' . $file->getFilename();

                    if (File::exists($target) && ! $this->option('force')) {
                        $skipped++;

                        continue;
                    }

                    File::copy($file->getPathname(), $target);
                    $copied++;
                }
            },
            'Copying migrations...'
        );

        if ($copied > 0) {
            info("{$copied} migration(s) copied → database/migrations/");
        }

        if ($skipped > 0) {
            warning("{$skipped} migration(s) skipped (use --force to overwrite)");
        }
    }
}
