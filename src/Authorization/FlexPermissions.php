<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use IvanMercedes\FlexFields\Models\Entity;

/**
 * Per-entity permissions checked through Laravel's Gate.
 *
 * Every entity exposes abilities such as "view_any_flex_products" or
 * "publish_flex_products" (format configurable). Because they go through the
 * Gate, they work with spatie/laravel-permission, Filament Shield or any
 * Gate::define()/Gate::before() logic of the host application.
 *
 * The same checks are used by Filament resources, AI tools and the REST API.
 */
class FlexPermissions
{
    public const VIEW_ANY = 'view_any';

    public const VIEW = 'view';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const RESTORE = 'restore';

    public const FORCE_DELETE = 'force_delete';

    public const PUBLISH = 'publish';

    public const IMPORT = 'import';

    public const EXPORT = 'export';

    /**
     * Map of Filament/Laravel policy methods to FlexFields abilities.
     *
     * @var array<string, string>
     */
    public const POLICY_ABILITY_MAP = [
        'viewAny' => self::VIEW_ANY,
        'view' => self::VIEW,
        'create' => self::CREATE,
        'update' => self::UPDATE,
        'replicate' => self::CREATE,
        'reorder' => self::UPDATE,
        'delete' => self::DELETE,
        'deleteAny' => self::DELETE,
        'restore' => self::RESTORE,
        'restoreAny' => self::RESTORE,
        'forceDelete' => self::FORCE_DELETE,
        'forceDeleteAny' => self::FORCE_DELETE,
    ];

    public static function enabled(): bool
    {
        return (bool) config('flex-fields.permissions.enabled', false);
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return array_values((array) config('flex-fields.permissions.abilities', [
            self::VIEW_ANY, self::VIEW, self::CREATE, self::UPDATE, self::DELETE,
            self::RESTORE, self::FORCE_DELETE, self::PUBLISH, self::IMPORT, self::EXPORT,
        ]));
    }

    public static function permissionName(string $ability, Entity | string $entity): string
    {
        $slug = $entity instanceof Entity ? $entity->slug : $entity;
        $format = (string) config('flex-fields.permissions.format', '{ability}_flex_{entity}');

        return strtr($format, [
            '{ability}' => $ability,
            '{entity}' => str_replace('-', '_', (string) $slug),
        ]);
    }

    public static function structurePermission(): string
    {
        return (string) config('flex-fields.permissions.structure_permission', 'manage_flex_structure');
    }

    /**
     * All permission names for the given entities (defaults to every entity).
     *
     * @param  iterable<Entity>|null  $entities
     * @return array<int, string>
     */
    public static function all(?iterable $entities = null): array
    {
        $entities ??= Entity::query()->orderBy('slug')->get();
        $permissions = [self::structurePermission()];

        foreach ($entities as $entity) {
            foreach (self::abilities() as $ability) {
                $permissions[] = self::permissionName($ability, $entity);
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Whether the user may perform an ability on an entity's records.
     * Always true while permissions are disabled.
     */
    public static function allows(?Authenticatable $user, string $ability, Entity | string $entity): bool
    {
        if (! self::enabled()) {
            return true;
        }

        $user ??= Auth::user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->check(self::permissionName($ability, $entity));
    }

    /**
     * Whether the user may manage structure (entities, fields, groups, categories).
     */
    public static function allowsStructure(?Authenticatable $user = null): bool
    {
        if (! self::enabled()) {
            return true;
        }

        $user ??= Auth::user();

        return $user !== null && Gate::forUser($user)->check(self::structurePermission());
    }

    /**
     * Translate a policy method (viewAny, forceDelete...) into a FlexFields ability.
     */
    public static function abilityForPolicyMethod(string $method): ?string
    {
        return self::POLICY_ABILITY_MAP[$method] ?? null;
    }
}
