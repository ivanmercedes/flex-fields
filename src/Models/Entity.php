<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\Traits\BelongsToFlexTenant;

/**
 * Entity — like a "Post Type" in WordPress/ACF.
 * Defines a data structure (e.g. "Producto", "Empleado", "Evento").
 */
class Entity extends Model
{
    use BelongsToFlexTenant;
    use HasFactory;

    protected $table = 'ff_entities';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_active',
        'show_in_menu',
        'menu_order',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_menu' => 'boolean',
        'menu_order' => 'integer',
        'settings' => 'array',
    ];

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class)->orderBy('order');
    }

    public function records(): HasMany
    {
        return $this->hasMany(EntityRecord::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(EntityCategory::class);
    }

    public function getActiveFieldsAttribute()
    {
        return $this->customFields()->where('is_active', true)->get();
    }

    public function getRecordsCountAttribute(): int
    {
        return $this->records()->count();
    }

    // Auto-generate slug from name
    protected static function booted(): void
    {
        $generateUniqueSlug = function (Entity $entity) {
            if (empty($entity->slug)) {
                $slug = Str::slug($entity->name);
                $originalSlug = $slug;
                $count = 1;

                while (static::where('slug', $slug)->where('id', '!=', $entity->id)->exists()) {
                    $slug = $originalSlug . '-' . $count;
                    $count++;
                }

                $entity->slug = $slug;
            }
        };

        static::creating($generateUniqueSlug);
        static::updating($generateUniqueSlug);
    }
}
