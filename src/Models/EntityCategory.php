<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Models\Traits\BelongsToFlexTenant;
use IvanMercedes\FlexFields\Support\FlexModels;

class EntityCategory extends Model
{
    use BelongsToFlexTenant;
    use HasFactory;

    protected $table = 'ff_entity_categories';

    protected $fillable = [
        'tenant_id',
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
            FlexModels::record(),
            'ff_entity_record_category',
            'entity_category_id',
            'entity_record_id'
        );
    }

    protected static function booted(): void
    {
        $generateUniqueSlug = function (EntityCategory $category) {
            if (empty($category->slug)) {
                $slug = Str::slug($category->name);
                $originalSlug = $slug;
                $count = 1;

                while (static::where('slug', $slug)
                    ->where('entity_id', $category->entity_id)
                    ->where('id', '!=', $category->id)
                    ->exists()) {
                    $slug = $originalSlug . '-' . $count;
                    $count++;
                }

                $category->slug = $slug;
            }
        };

        static::creating($generateUniqueSlug);
        static::updating($generateUniqueSlug);
        static::saving(static::ensureSameTenant(...));
    }

    /**
     * A category can only belong to an entity (and parent) of its own tenant,
     * whatever saved it: panel forms, the API or the AI tools.
     */
    protected static function ensureSameTenant(EntityCategory $category): void
    {
        $column = (string) config('flex-fields.tenancy.tenant_column', 'tenant_id');
        $tenant = $category->getAttribute($column);

        $entityTenant = Entity::query()->whereKey($category->entity_id)->value($column);

        if ($category->entity_id !== null && $entityTenant !== null && (string) $entityTenant !== (string) $tenant) {
            throw ValidationException::withMessages(['entity_id' => 'The selected entity belongs to another tenant.']);
        }

        if ($category->parent_id !== null && ! static::query()->whereKey($category->parent_id)->where('entity_id', $category->entity_id)->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'The parent category must belong to the same entity.']);
        }
    }
}
