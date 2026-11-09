<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Where a tenant's uploads live on the uploads disk (see uploads.directory_pattern),
 * so file values coming from the API, the AI tools or form state can be kept to
 * the files of their own tenant.
 */
class UploadPaths
{
    /**
     * The tenant placeholders of the directory pattern, resolved for a tenant.
     *
     * @return array{'{tenant_id}': string, '{tenant_slug}': string, '{tenant_key}': string}
     */
    public static function tenantReplacements(?Model $tenant): array
    {
        $tenantId = '';
        $tenantSlug = '';

        if ($tenant) {
            $attribute = (string) config('flex-fields.uploads.tenant_key_attribute', 'slug');
            $tenantId = (string) ($tenant->getKey() ?? '');
            $rawSlug = $tenant->{$attribute} ?? $tenant->slug ?? $tenant->id ?? $tenantId;
            $tenantSlug = Str::slug((string) $rawSlug);
        }

        return [
            '{tenant_id}' => $tenantId,
            '{tenant_slug}' => $tenantSlug,
            '{tenant_key}' => $tenantSlug ?: $tenantId,
        ];
    }

    /**
     * The directory every upload of the tenant lives under (the pattern up to its
     * tenant placeholder), or null when uploads are not separated by tenant.
     */
    public static function tenantRoot(int | string | null $tenantId): ?string
    {
        if ($tenantId === null || ! config('flex-fields.tenancy.enabled', false)) {
            return null;
        }

        $pattern = (string) config('flex-fields.uploads.directory_pattern', 'flex-fields/{tenant_slug}/{entity_slug}/{field_key}');

        if (preg_match('/^(.*?\{(?:tenant_id|tenant_slug|tenant_key)\})/', $pattern, $matches) !== 1) {
            return null;
        }

        /** @var class-string<Model>|null $model */
        $model = config('flex-fields.tenancy.tenant_model');
        $tenant = $model && class_exists($model) ? $model::query()->find($tenantId) : null;

        if (! $tenant instanceof Model) {
            return null;
        }

        $root = trim(strtr($matches[1], static::tenantReplacements($tenant)), '/');

        return $root === '' ? null : $root;
    }

    /**
     * Whether a stored file value may be used by the tenant: an absolute URL (an
     * external file), or a path under the tenant's upload directory.
     */
    public static function allows(int | string | null $tenantId, string $path): bool
    {
        if (preg_match('#^([a-z][a-z0-9+.-]*:)?//#i', $path) === 1) {
            return true;
        }

        $path = ltrim($path, '/');

        if (in_array('..', explode('/', $path), true)) {
            return false;
        }

        $root = static::tenantRoot($tenantId);

        return $root === null || str_starts_with($path, $root . '/');
    }
}
