<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models\Traits;

use App\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Scope a query to only include records of the current Filament tenant.
     */
    public function scopeCurrentTenant(Builder $query): void
    {
        if (config('flex-fields.tenancy.enabled', false)) {
            $tenantColumn = config('flex-fields.tenancy.tenant_column', 'tenant_id');
            if ($tenant = Filament::getTenant()) {
                $query->where($this->getTable() . '.' . $tenantColumn, $tenant->getKey());
            }
        }
    }
}
