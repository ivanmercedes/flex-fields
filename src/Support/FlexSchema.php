<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Detects which optional tables/columns exist so the package keeps working
 * right after an upgrade, before `php artisan migrate` has been run.
 * Results are memoized per process.
 */
class FlexSchema
{
    /** @var array<string, bool> */
    protected static array $cache = [];

    /**
     * Field groups (ff_field_groups + ff_custom_fields.field_group_id).
     */
    public static function hasFieldGroups(): bool
    {
        return static::remember('field_groups', fn () => Schema::hasTable('ff_field_groups')
            && Schema::hasColumn('ff_custom_fields', 'field_group_id'));
    }

    /**
     * Record history (ff_record_revisions).
     */
    public static function hasRevisions(): bool
    {
        return static::remember('revisions', fn () => Schema::hasTable('ff_record_revisions'));
    }

    public static function flush(): void
    {
        static::$cache = [];
    }

    protected static function remember(string $key, callable $check): bool
    {
        if (! array_key_exists($key, static::$cache)) {
            try {
                static::$cache[$key] = (bool) $check();
            } catch (Throwable) {
                // No database connection available (e.g. building config cache): assume not migrated, don't memoize.
                return false;
            }
        }

        return static::$cache[$key];
    }
}
