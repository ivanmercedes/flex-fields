<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\Traits\BelongsToFlexTenant;

/**
 * EntityRecord — a single row/entry for a given Entity.
 * Think of this like a "post" belonging to a "post type".
 * The actual field values are stored in FieldValue (EAV pattern).
 */
class EntityRecord extends Model
{
    use BelongsToFlexTenant;
    use HasFactory;

    protected $table = 'ff_entity_records';

    protected $fillable = [
        'tenant_id',
        'entity_id',
        'title',
        'slug',
        'status',   // draft | published | archived
        'order',
        'meta',     // JSON: any extra metadata
    ];

    protected $casts = [
        'order' => 'integer',
        'meta' => 'array',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(FieldValue::class, 'entity_record_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            EntityCategory::class,
            'ff_entity_record_category',
            'entity_record_id',
            'entity_category_id'
        );
    }

    /**
     * Get all field values as a flat key=>value array.
     */
    public function getDataAttribute(): array
    {
        return $this->fieldValues
            ->mapWithKeys(fn ($fv) => [$fv->customField->key ?? $fv->custom_field_id => $fv->getCastedValue()])
            ->toArray();
    }

    /**
     * Get value for a specific field key.
     */
    public function getValue(string $key): mixed
    {
        $fv = $this->fieldValues
            ->first(fn ($v) => optional($v->customField)->key === $key);

        return $fv ? $fv->getCastedValue() : null;
    }

    /**
     * Set or update a field value by key.
     */
    public function setValue(string $key, mixed $value): void
    {
        $field = $this->entity->customFields()->where('key', $key)->first();
        if (! $field) {
            return;
        }

        $this->fieldValues()->updateOrCreate(
            ['custom_field_id' => $field->id],
            ['value' => is_array($value) ? json_encode($value) : $value]
        );
    }

    protected static function booted(): void
    {
        $generateUniqueSlug = function (EntityRecord $record) {
            if (empty($record->slug)) {
                $slug = Str::slug($record->title);
                $originalSlug = $slug;
                $count = 1;

                while (static::where('slug', $slug)
                    ->where('entity_id', $record->entity_id)
                    ->where('id', '!=', $record->id)
                    ->exists()) {
                    $slug = $originalSlug . '-' . $count;
                    $count++;
                }

                $record->slug = $slug;
            }
        };

        static::creating($generateUniqueSlug);
        static::updating($generateUniqueSlug);
    }
}
