<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models\Traits;

use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToFlexTenant
{
    /**
     * Get the tenant relationship for this model dynamically based on config.
     */
    public function tenant(): BelongsTo
    {
        $tenantModel = config('flex-fields.tenancy.tenant_model', Team::class);
        $tenantColumn = config('flex-fields.tenancy.tenant_column', 'tenant_id');

        return $this->belongsTo($tenantModel, $tenantColumn);
    }
}
