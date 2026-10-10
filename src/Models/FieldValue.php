<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use IvanMercedes\FlexFields\Models\Traits\BelongsToFlexTenant;
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\FlexModels;

/**
 * FieldValue — stores the actual value for a field in a record (EAV).
 */
class FieldValue extends Model
{
    use BelongsToFlexTenant;

    protected $table = 'ff_field_values';

    protected $fillable = [
        'tenant_id',
        'entity_record_id',
        'custom_field_id',
        'value',
    ];

    public function entityRecord(): BelongsTo
    {
        return $this->belongsTo(FlexModels::record(), 'entity_record_id');
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    /**
     * Return value cast to the proper PHP type based on field type.
     */
    public function getCastedValue(): mixed
    {
        return FieldTypes::cast($this->customField, $this->value);
    }
}
