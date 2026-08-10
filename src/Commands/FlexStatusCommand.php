<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use IvanMercedes\FlexFields\Facades\FlexFields;

class FlexStatusCommand extends Command
{
    protected $signature = 'flex:status';

    protected $description = 'Show entity, custom field, record count, and configuration summary for FlexFields';

    public function handle(): int
    {
        $this->newLine();
        $this->info('  ⚡ FlexFields — System Status Overview');
        $this->newLine();

        $stats = FlexFields::status();

        // Entities Table
        $this->line('  <fg=yellow;options=bold>Entities Overview</>');
        $this->table(
            ['Total', 'Active', 'Inactive'],
            [
                [
                    $stats['entities']['total'],
                    $stats['entities']['active'],
                    $stats['entities']['inactive'],
                ],
            ]
        );

        // Custom Fields Table
        $this->line('  <fg=yellow;options=bold>Custom Fields Overview</>');
        $this->table(
            ['Total', 'Active', 'Inactive'],
            [
                [
                    $stats['fields']['total'],
                    $stats['fields']['active'],
                    $stats['fields']['inactive'],
                ],
            ]
        );

        // Records Table
        $this->line('  <fg=yellow;options=bold>Entity Records Overview</>');
        $this->table(
            ['Total Records', 'Published', 'Draft', 'Trashed'],
            [
                [
                    $stats['records']['total'],
                    $stats['records']['published'],
                    $stats['records']['draft'],
                    $stats['records']['trashed'],
                ],
            ]
        );

        // Configuration Summary
        $this->line('  <fg=yellow;options=bold>System Configuration</>');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Multi-Tenancy', $stats['system']['tenancy_enabled'] ? 'Enabled' : 'Disabled'],
                ['Field Caching', $stats['system']['cache_enabled'] ? 'Enabled' : 'Disabled'],
                ['Upload Storage Disk', $stats['system']['uploads_disk']],
                ['Upload Visibility', $stats['system']['uploads_visibility']],
            ]
        );

        $this->newLine();

        return self::SUCCESS;
    }
}
