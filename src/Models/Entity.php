<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\Traits\BelongsToFlexTenant;
use IvanMercedes\FlexFields\Support\FieldCache;
use Throwable;

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

    /**
     * Generate a unique slug for an entity within its tenant.
     * If the slug already exists within the tenant, appends -1, -2, etc.
     */
    public static function generateUniqueSlug(
        string $name,
        ?int $tenantId = null,
        ?int $ignoreId = null,
        ?string $preferredSlug = null
    ): string {
        $baseSlug = ! empty($preferredSlug)
            ? Str::slug($preferredSlug)
            : Str::slug($name);

        if (empty($baseSlug)) {
            $baseSlug = 'entity';
        }

        $tenantColumn = config('flex-fields.tenancy.tenant_column', 'tenant_id');

        if ($tenantId === null && config('flex-fields.tenancy.enabled', false)) {
            if (class_exists(Filament::class) && Filament::hasTenancy()) {
                try {
                    $tenantId = Filament::getTenant()?->getKey();
                } catch (Throwable) {
                }
            }
        }

        $slug = $baseSlug;
        $count = 1;

        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->when(
                    $tenantId !== null,
                    fn ($q) => $q->where($tenantColumn, $tenantId),
                    fn ($q) => config('flex-fields.tenancy.enabled', false) ? $q->whereNull($tenantColumn) : $q
                )
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

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
        return FieldCache::getForEntity($this);
    }

    public function getRecordsCountAttribute(): int
    {
        return $this->records()->count();
    }

    // Auto-generate slug from name & invalidate cache on changes
    protected static function booted(): void
    {
        $generateUniqueSlug = function (Entity $entity) {
            $tenantColumn = config('flex-fields.tenancy.tenant_column', 'tenant_id');

            if ($entity->{$tenantColumn} === null && config('flex-fields.tenancy.enabled', false)) {
                if (class_exists(Filament::class) && Filament::hasTenancy()) {
                    try {
                        if ($tenant = Filament::getTenant()) {
                            $entity->{$tenantColumn} = $tenant->getKey();
                        }
                    } catch (Throwable) {
                    }
                }
            }

            $tenantId = $entity->{$tenantColumn} !== null ? (int) $entity->{$tenantColumn} : null;

            if (empty($entity->slug)) {
                $entity->slug = static::generateUniqueSlug(
                    name: (string) $entity->name,
                    tenantId: $tenantId,
                    ignoreId: $entity->id,
                );
            } elseif (! $entity->exists) {
                $entity->slug = static::generateUniqueSlug(
                    name: (string) $entity->name,
                    tenantId: $tenantId,
                    ignoreId: null,
                    preferredSlug: (string) $entity->slug,
                );
            }
        };

        $invalidateCache = function (Entity $entity) {
            FieldCache::forgetForEntity($entity);
        };

        static::creating($generateUniqueSlug);
        static::updating($generateUniqueSlug);

        static::saved($invalidateCache);
        static::deleted($invalidateCache);
    }
}
