<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EntityCategory extends Model
{
    use HasFactory;

    protected $table = 'ff_entity_categories';

    protected $fillable = [
        'entity_id',
        'parent_id',
        'name',
        'slug',
        'description',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function records(): BelongsToMany
    {
        return $this->belongsToMany(
            EntityRecord::class,
            'ff_entity_record_category',
            'entity_category_id',
            'entity_record_id'
        );
    }

    protected static function booted(): void
    {
        static::creating(function (EntityCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
