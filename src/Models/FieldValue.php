<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FieldValue — stores the actual value for a field in a record (EAV).
 */
class FieldValue extends Model
{
    protected $table = 'ff_field_values';

    protected $fillable = [
        'entity_record_id',
        'custom_field_id',
        'value',
    ];

    public function entityRecord(): BelongsTo
    {
        return $this->belongsTo(EntityRecord::class, 'entity_record_id');
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
        $raw = $this->value;
        $type = optional($this->customField)->type ?? 'text';

        return match ($type) {
            'number' => is_numeric($raw) ? (float) $raw : null,
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'date' => $raw ? Carbon::parse($raw)->toDateString() : null,
            'datetime' => $raw ? Carbon::parse($raw)->toDateTimeString() : null,
            'json', 'multiselect', 'tags' => is_string($raw) ? json_decode($raw, true) : $raw,
            'image', 'file' => (is_string($raw) && (str_starts_with($raw, '{') || str_starts_with($raw, '['))) 
                ? (array_values(json_decode($raw, true) ?? [])[0] ?? $raw) 
                : $raw,
            default => $raw,
        };
    }
}
