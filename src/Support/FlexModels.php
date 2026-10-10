<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use IvanMercedes\FlexFields\Models\EntityRecord;

/**
 * Resolves model classes that can be swapped through config('flex-fields.models').
 */
class FlexModels
{
    /**
     * @return class-string<EntityRecord>
     */
    public static function record(): string
    {
        $class = config('flex-fields.models.record', EntityRecord::class);

        return is_string($class) && is_a($class, EntityRecord::class, true) ? $class : EntityRecord::class;
    }
}
