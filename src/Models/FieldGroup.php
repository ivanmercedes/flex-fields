<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use IvanMercedes\FlexFields\Models\Traits\BelongsToFlexTenant;
use IvanMercedes\FlexFields\Support\FieldCache;

/**
 * FieldGroup — a labeled, collapsible section that groups custom fields in a form.
 */
class FieldGroup extends Model
{
    use BelongsToFlexTenant;

    protected $table = 'ff_field_groups';

    protected $fillable = [
        'tenant_id',
        'entity_id',
        'label',
        'key',
        'description',
        'order',
        'is_collapsible',
        'is_collapsed',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_collapsible' => 'boolean',
        'is_collapsed' => 'boolean',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomField::class, 'field_group_id')->orderBy('order');
    }

    protected static function booted(): void
    {
        $invalidateCache = function (FieldGroup $group) {
            if ($group->entity_id) {
                FieldCache::forgetForEntity($group->entity_id);
            }
        };

        static::saved($invalidateCache);
        static::deleted($invalidateCache);
    }
}
