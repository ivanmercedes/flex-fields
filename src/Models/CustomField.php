<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * CustomField — a field definition attached to an Entity.
 * Stores metadata: type, label, key, options, validation rules, order, etc.
 */
class CustomField extends Model
{
    use HasFactory;

    protected $table = 'ff_custom_fields';

    protected $fillable = [
        'entity_id',
        'label',
        'key',
        'type',
        'description',
        'placeholder',
        'default_value',
        'options',           // JSON: for select/multiselect
        'validation_rules',  // JSON: ['required', 'max:255', ...]
        'settings',          // JSON: extra type-specific config
        'order',
        'is_required',
        'is_active',
        'is_searchable',
        'is_shown_in_list',
        'width',             // full | half | third
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'settings' => 'array',
        'order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'is_searchable' => 'boolean',
        'is_shown_in_list' => 'boolean',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(FieldValue::class, 'custom_field_id');
    }

    /**
     * Returns Filament column type string for table builders.
     */
    public function getFilamentColumnType(): string
    {
        return match ($this->type) {
            'boolean' => 'IconColumn',
            'image' => 'ImageColumn',
            'color' => 'ColorColumn',
            'date' => 'TextColumn',
            'datetime' => 'TextColumn',
            default => 'TextColumn',
        };
    }

    /**
     * Returns the PHP cast type for this field.
     */
    public function getFieldCastType(): string
    {
        return match ($this->type) {
            'number' => 'float',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'datetime',
            'json' => 'array',
            'select' => 'string',
            'multiselect' => 'array',
            'tags' => 'array',
            default => 'string',
        };
    }

    /**
     * Parsed options array for select/multiselect fields.
     * Supports both ['key' => 'Label'] and ['value' => 'x', 'label' => 'y'] formats.
     */
    public function getParsedOptionsAttribute(): array
    {
        $opts = $this->options ?? [];
        if (empty($opts)) {
            return [];
        }
        // If options are stored as [{value, label}] convert to [value => label]
        if (isset($opts[0]) && is_array($opts[0])) {
            return collect($opts)->pluck('label', 'value')->toArray();
        }

        return $opts;
    }

    protected static function booted(): void
    {
        static::creating(function (CustomField $field) {
            if (empty($field->key)) {
                $field->key = Str::snake($field->label);
            }
            if (is_null($field->order)) {
                $field->order = static::where('entity_id', $field->entity_id)->max('order') + 1;
            }
        });
    }
}
