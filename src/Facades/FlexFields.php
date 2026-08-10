<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Facades;

use Illuminate\Support\Facades\Facade;
use IvanMercedes\FlexFields\FlexFieldsManager;

/**
 * @method static \IvanMercedes\FlexFields\Support\EntityQuery entity(\IvanMercedes\FlexFields\Models\Entity|string|int $entity)
 * @method static \Illuminate\Database\Eloquent\Builder entities()
 * @method static void makeEntity(string $name, \Closure $callback)
 * @method static void updateEntity(string $slug, \Closure $callback)
 * @method static void dropEntity(string $slug)
 * @method static array form(\IvanMercedes\FlexFields\Models\Entity|string|int $entity)
 * @method static bool clearCache(?string $entitySlug = null)
 * @method static array status()
 *
 * @see FlexFieldsManager
 */
class FlexFields extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'flex-fields';
    }
}
