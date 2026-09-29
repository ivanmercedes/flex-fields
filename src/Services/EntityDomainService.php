<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityCategory;
use IvanMercedes\FlexFields\Support\FieldCache;
use RuntimeException;
use Throwable;

class EntityDomainService
{
    /**
     * List all entities with optional filters.
     *
     * @param  array{active_only?: bool, search?: ?string}  $filters
     * @return Collection<int, Entity>
     */
    public function list(array $filters = [], ?int $tenantId = null): Collection
    {
        $query = $this->query($tenantId);

        if (! empty($filters['active_only'])) {
            $query->where('is_active', true);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('menu_order')->orderBy('name')->get();
    }

    /**
     * Find an entity by ID or slug.
     */
    public function find(int | string $idOrSlug, ?int $tenantId = null): ?Entity
    {
        $query = $this->query($tenantId);

        if (is_numeric($idOrSlug)) {
            return $query->where('id', (int) $idOrSlug)->first();
        }

        return $query->where('slug', (string) $idOrSlug)->first();
    }

    /**
     * Find an entity or throw an exception.
     *
     * @throws RuntimeException
     */
    public function findOrFail(int | string $idOrSlug, ?int $tenantId = null): Entity
    {
        $entity = $this->find($idOrSlug, $tenantId);

        if (! $entity) {
            throw new RuntimeException("Entity [{$idOrSlug}] not found or not accessible.");
        }

        return $entity;
    }

    /**
     * Introspect and return the complete schema definition for an entity.
     *
     * @return array<string, mixed>
     */
    public function getSchema(int | string $idOrSlug, ?int $tenantId = null): array
    {
        $entity = $this->findOrFail($idOrSlug, $tenantId);

        $fields = $entity->customFields()->orderBy('order')->get()->map(function (CustomField $field) {
            return [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'description' => $field->description,
                'placeholder' => $field->placeholder,
                'default_value' => $field->default_value,
                'is_required' => (bool) $field->is_required,
                'is_active' => (bool) $field->is_active,
                'is_searchable' => (bool) $field->is_searchable,
                'is_shown_in_list' => (bool) $field->is_shown_in_list,
                'width' => $field->width,
                'options' => $field->parsed_options,
                'validation_rules' => $field->validation_rules ?? [],
                'settings' => $field->settings ?? [],
                'repeater_subfields' => $field->settings['schema'] ?? null,
                'category_ids' => $field->category_ids ?? [],
            ];
        })->toArray();

        $categories = $entity->categories()->orderBy('name')->get()->map(function (EntityCategory $cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'parent_id' => $cat->parent_id,
                'description' => $cat->description,
            ];
        })->toArray();

        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'slug' => $entity->slug,
            'description' => $entity->description,
            'icon' => $entity->icon,
            'color' => $entity->color,
            'is_active' => (bool) $entity->is_active,
            'show_in_menu' => (bool) $entity->show_in_menu,
            'menu_order' => $entity->menu_order,
            'settings' => $entity->settings ?? [],
            'fields_count' => count($fields),
            'records_count' => $entity->records()->count(),
            'fields' => $fields,
            'categories' => $categories,
        ];
    }

    /**
     * Create a new entity.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?int $tenantId = null): Entity
    {
        $tenantId = $this->resolveTenantId($tenantId ?? ($attributes['tenant_id'] ?? null));

        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Entity name is required.');
        }

        $preferredSlug = ! empty($attributes['slug']) ? (string) $attributes['slug'] : null;

        $slug = Entity::generateUniqueSlug(
            name: $name,
            tenantId: $tenantId,
            ignoreId: null,
            preferredSlug: $preferredSlug,
        );

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
            'icon' => $attributes['icon'] ?? 'heroicon-o-cube',
            'color' => $attributes['color'] ?? '#6366f1',
            'is_active' => $attributes['is_active'] ?? true,
            'show_in_menu' => $attributes['show_in_menu'] ?? true,
            'menu_order' => (int) ($attributes['menu_order'] ?? 0),
            'settings' => $attributes['settings'] ?? [],
        ];

        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }

        return Entity::create($data);
    }

    /**
     * Update an existing entity.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int | string $idOrSlug, array $attributes, ?int $tenantId = null): Entity
    {
        $entity = $this->findOrFail($idOrSlug, $tenantId);
        $tenantId = $this->resolveTenantId($tenantId ?? ($entity->tenant_id ?? null));

        $allowed = [
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

        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $attributes)) {
                if ($key === 'slug') {
                    $newSlug = Str::slug((string) $attributes['slug']);
                    $query = $this->query($tenantId)
                        ->where('slug', $newSlug)
                        ->where('id', '!=', $entity->id);

                    if ($query->exists()) {
                        throw new RuntimeException("Entity slug [{$newSlug}] is already taken.");
                    }
                    $data['slug'] = $newSlug;
                } else {
                    $data[$key] = $attributes[$key];
                }
            }
        }

        if (! empty($data)) {
            $entity->update($data);
        }

        FieldCache::forgetForEntity($entity);

        return $entity->fresh();
    }

    /**
     * Delete an entity and its associated fields and records.
     */
    public function delete(int | string $idOrSlug, ?int $tenantId = null): bool
    {
        $entity = $this->findOrFail($idOrSlug, $tenantId);

        FieldCache::forgetForEntity($entity);

        $entity->customFields()->delete();
        $entity->records()->forceDelete();
        $entity->categories()->delete();

        return (bool) $entity->delete();
    }

    /**
     * Get a query builder scoped to tenant if applicable.
     *
     * @return Builder<Entity>
     */
    protected function query(?int $tenantId = null): Builder
    {
        $query = Entity::query();
        $resolvedTenantId = $this->resolveTenantId($tenantId);

        if (config('flex-fields.tenancy.enabled', false)) {
            $tenantColumn = config('flex-fields.tenancy.tenant_column', 'tenant_id');
            if ($resolvedTenantId !== null) {
                $query->where($tenantColumn, $resolvedTenantId);
            } else {
                $query->whereNull($tenantColumn);
            }
        } elseif ($resolvedTenantId !== null) {
            $tenantColumn = config('flex-fields.tenancy.tenant_column', 'tenant_id');
            $query->where($tenantColumn, $resolvedTenantId);
        }

        return $query;
    }

    /**
     * Resolve tenant ID from argument, context or config.
     */
    protected function resolveTenantId(?int $explicitTenantId = null): ?int
    {
        if ($explicitTenantId !== null) {
            return $explicitTenantId;
        }

        try {
            if (class_exists(Filament::class) && Filament::hasTenancy()) {
                $tenant = Filament::getTenant();

                return $tenant ? (int) $tenant->getKey() : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
