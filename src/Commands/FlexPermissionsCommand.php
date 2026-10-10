<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Models\Entity;

class FlexPermissionsCommand extends Command
{
    protected $signature = 'flex:permissions
                            {--entity=* : Limit to the given entity slugs}
                            {--sync : Create the permissions with spatie/laravel-permission (Filament Shield)}
                            {--guard= : Guard name used when syncing permissions}';

    protected $description = 'List (or sync) the per-entity permissions used by FlexFields';

    public function handle(): int
    {
        $query = Entity::query()->orderBy('slug');

        if ($slugs = $this->option('entity')) {
            $query->whereIn('slug', $slugs);
        }

        $permissions = FlexPermissions::all($query->get());

        if (! FlexPermissions::enabled()) {
            $this->components->warn('Permissions are disabled. Set flex-fields.permissions.enabled = true (FLEX_FIELDS_PERMISSIONS_ENABLED) to enforce them.');
        }

        if ($this->option('sync')) {
            return $this->sync($permissions);
        }

        $this->table(['Permission'], array_map(fn (string $permission) => [$permission], $permissions));
        $this->components->info(count($permissions) . ' permissions. Use --sync to create them with spatie/laravel-permission.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function sync(array $permissions): int
    {
        $permissionModel = 'Spatie\\Permission\\Models\\Permission';

        if (! class_exists($permissionModel)) {
            $this->components->error('spatie/laravel-permission is not installed. Install it (or Filament Shield) to sync permissions.');

            return self::FAILURE;
        }

        $guard = $this->option('guard') ?: config('auth.defaults.guard', 'web');

        foreach ($permissions as $permission) {
            $permissionModel::findOrCreate($permission, $guard);
        }

        $this->components->info(count($permissions) . " permissions synced for guard [{$guard}].");

        return self::SUCCESS;
    }
}
