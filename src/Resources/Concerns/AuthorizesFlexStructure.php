<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use UnitEnum;

/**
 * Structural resources (entities, fields, categories) require the structure
 * permission when FlexFields permissions are enabled.
 */
trait AuthorizesFlexStructure
{
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        if (FlexPermissions::enabled() && ! FlexPermissions::allowsStructure()) {
            return Response::deny();
        }

        return parent::getAuthorizationResponse($action, $record);
    }
}
