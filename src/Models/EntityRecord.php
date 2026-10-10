<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\Traits\BelongsToFlexTenant;
use IvanMercedes\FlexFields\Services\RecordHistoryService;
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\FlexSchema;

/**
 * EntityRecord — a single row/entry for a given Entity.
 * Think of this like a "post" belonging to a "post type".
 * The actual field values are stored in FieldValue (EAV pattern).
 */
class EntityRecord extends Model
{
    use BelongsToFlexTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ff_entity_records';

    protected $fillable = [
        'tenant_id',
        'entity_id',
        'title',
        'slug',
        'status',   // draft | published | archived
        'order',
        'meta',     // JSON: any extra metadata
        'created_at',
        'updated_at',
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

    public function revisions(): HasMany
    {
        return $this->hasMany(RecordRevision::class, 'entity_record_id')->orderByDesc('id');
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
            ['value' => FieldTypes::serialize($value)]
        );
    }

    /**
     * Get the value of a translatable field in a given locale (with fallback).
     */
    public function getTranslation(string $key, ?string $locale = null, bool $useFallback = true): mixed
    {
        $value = $this->getValue($key);

        if (! is_array($value)) {
            return $value;
        }

        $locale ??= app()->getLocale();

        if (array_key_exists($locale, $value) && $value[$locale] !== null && $value[$locale] !== '') {
            return $value[$locale];
        }

        return $useFallback ? ($value[FieldTypes::fallbackLocale()] ?? null) : null;
    }

    /**
     * Resolve the records linked through a "relationship" field.
     *
     * @return Collection<int, EntityRecord>
     */
    public function related(string $key): Collection
    {
        $field = $this->entity?->customFields()->where('key', $key)->first();

        if (! $field || $field->type !== 'relationship') {
            return new Collection;
        }

        $ids = array_filter((array) $this->getValue($key));

        if (empty($ids)) {
            return new Collection;
        }

        $relatedEntity = Entity::query()
            ->where('slug', $field->settings['related_entity'] ?? null)
            ->when($this->tenant_id !== null, fn ($q) => $q->where('tenant_id', $this->tenant_id))
            ->first();

        if (! $relatedEntity) {
            return new Collection;
        }

        return $relatedEntity->records()->whereIn('id', $ids)->get();
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

        static::deleted(function (EntityRecord $record) {
            if (! $record->isForceDeleting()) {
                app(RecordHistoryService::class)->record($record, RecordHistoryService::EVENT_DELETED);
            }
        });

        static::restored(function (EntityRecord $record) {
            app(RecordHistoryService::class)->record($record, RecordHistoryService::EVENT_RESTORED);
        });

        static::forceDeleted(function (EntityRecord $record) {
            if (! FlexSchema::hasRevisions()) {
                return;
            }

            RecordRevision::where('entity_record_id', $record->getKey())->delete();
        });
    }
}
