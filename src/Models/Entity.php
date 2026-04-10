<?php

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Entity — like a "Post Type" in WordPress/ACF.
 * Defines a data structure (e.g. "Producto", "Empleado", "Evento").
 */
class Entity extends Model
{
    use HasFactory;

    protected $table = 'ff_entities';

    protected $fillable = [
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
        'is_active'    => 'boolean',
        'show_in_menu' => 'boolean',
        'menu_order'   => 'integer',
        'settings'     => 'array',
    ];

    // Auto-generate slug from name
    protected static function booted(): void
    {
        static::creating(function (Entity $entity) {
            if (empty($entity->slug)) {
                $entity->slug = Str::slug($entity->name);
            }
        });
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class)->orderBy('order');
    }

    public function records(): HasMany
    {
        return $this->hasMany(EntityRecord::class);
    }

    public function getActiveFieldsAttribute()
    {
        return $this->customFields()->where('is_active', true)->get();
    }

    public function getRecordsCountAttribute(): int
    {
        return $this->records()->count();
    }
}
