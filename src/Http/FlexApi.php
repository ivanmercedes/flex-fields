<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Http;

use Closure;
use Illuminate\Http\Request;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\EntityDomainService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Request context for the headless REST API: tenant resolution, Sanctum token
 * abilities, entity access and per-entity permissions.
 *
 * The tenant always comes from the application (resolver), never from the client.
 */
class FlexApi
{
    protected static ?Closure $tenantResolver = null;

    /**
     * Register how the tenant key is resolved from the request, e.g.:
     *   FlexApi::resolveTenantUsing(fn (Request $request) => $request->user()->current_team_id);
     */
    public static function resolveTenantUsing(?Closure $resolver): void
    {
        static::$tenantResolver = $resolver;
    }

    public static function resolveTenant(Request $request): ?int
    {
        $resolver = static::$tenantResolver;

        if ($resolver === null && ($class = config('flex-fields.api.tenant_resolver'))) {
            $resolver = Closure::fromCallable(app($class));
        }

        if ($resolver === null) {
            if (config('flex-fields.tenancy.enabled', false)) {
                throw new HttpException(403, 'The FlexFields API tenant resolver is not configured.');
            }

            return null;
        }

        $tenant = $resolver($request);

        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            $tenant = $tenant->getKey();
        }

        if ($tenant === null && config('flex-fields.tenancy.enabled', false)) {
            throw new HttpException(403, 'No tenant could be resolved for this request.');
        }

        return $tenant === null ? null : (int) $tenant;
    }

    /**
     * Check the Sanctum token ability ('read', 'write' or 'delete') when the
     * request is authenticated with a personal access token.
     */
    public static function authorizeToken(Request $request, string $operation): void
    {
        $user = $request->user();
        $ability = config("flex-fields.api.token_abilities.{$operation}");

        if (! $user || ! $ability || ! method_exists($user, 'tokenCan') || ! method_exists($user, 'currentAccessToken')) {
            return;
        }

        if ($user->currentAccessToken() !== null && ! $user->tokenCan($ability)) {
            throw new HttpException(403, "This token does not have the [{$ability}] ability.");
        }
    }

    public static function authorize(Request $request, string $ability, Entity $entity): void
    {
        if (! FlexPermissions::allows($request->user(), $ability, $entity)) {
            throw new HttpException(403, 'This action is unauthorized.');
        }
    }

    /**
     * Resolve an entity accessible through the API for the request's tenant.
     */
    public static function entity(Request $request, string $identifier): Entity
    {
        $entity = app(EntityDomainService::class)->find($identifier, static::resolveTenant($request));

        $allowed = config('flex-fields.api.allowed_entities');

        if (! $entity || ! $entity->is_active || ($allowed !== null && ! in_array($entity->slug, (array) $allowed, true))) {
            throw new HttpException(404, "Entity [{$identifier}] not found.");
        }

        return $entity;
    }
}
