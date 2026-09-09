<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\EntityCategory;
use RuntimeException;

class CategoryDomainService
{
    public function __construct(
        protected EntityDomainService $entityService = new EntityDomainService
    ) {}

    /**
     * List all categories for an entity.
     *
     * @return Collection<int, EntityCategory>
     */
    public function list(int | string $entityIdOrSlug, ?int $tenantId = null): Collection
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        return $entity->categories()->with('children')->orderBy('name')->get();
    }

    /**
     * Find a category by ID or slug within an entity.
     */
    public function find(int | string $entityIdOrSlug, int | string $categoryIdOrSlug, ?int $tenantId = null): ?EntityCategory
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        if (is_numeric($categoryIdOrSlug)) {
            return $entity->categories()->where('id', (int) $categoryIdOrSlug)->first();
        }

        return $entity->categories()->where('slug', (string) $categoryIdOrSlug)->first();
    }

    /**
     * Find a category or throw an exception.
     *
     * @throws RuntimeException
     */
    public function findOrFail(int | string $entityIdOrSlug, int | string $categoryIdOrSlug, ?int $tenantId = null): EntityCategory
    {
        $category = $this->find($entityIdOrSlug, $categoryIdOrSlug, $tenantId);

        if (! $category) {
            throw new RuntimeException("Category [{$categoryIdOrSlug}] not found on entity [{$entityIdOrSlug}].");
        }

        return $category;
    }

    /**
     * Create a category for an entity.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(int | string $entityIdOrSlug, array $attributes, ?int $tenantId = null): EntityCategory
    {
        $entity = $this->entityService->findOrFail($entityIdOrSlug, $tenantId);

        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Category name is required.');
        }

        $slug = ! empty($attributes['slug'])
            ? Str::slug((string) $attributes['slug'])
            : Str::slug($name);

        $originalSlug = $slug;
        $counter = 1;
        while ($entity->categories()->where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $entity->categories()->create([
            'tenant_id' => $entity->tenant_id,
            'parent_id' => $attributes['parent_id'] ?? null,
            'name' => $name,
            'slug' => $slug,
            'description' => $attributes['description'] ?? null,
        ]);
    }

    /**
     * Update an existing category.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int | string $entityIdOrSlug, int | string $categoryIdOrSlug, array $attributes, ?int $tenantId = null): EntityCategory
    {
        $category = $this->findOrFail($entityIdOrSlug, $categoryIdOrSlug, $tenantId);
        $entity = $category->entity;

        $data = [];
        if (array_key_exists('name', $attributes)) {
            $data['name'] = trim((string) $attributes['name']);
        }
        if (array_key_exists('slug', $attributes)) {
            $newSlug = Str::slug((string) $attributes['slug']);
            $exists = $entity->categories()
                ->where('slug', $newSlug)
                ->where('id', '!=', $category->id)
                ->exists();

            if ($exists) {
                throw new RuntimeException("Category slug [{$newSlug}] is already taken for this entity.");
            }
            $data['slug'] = $newSlug;
        }
        if (array_key_exists('parent_id', $attributes)) {
            $parentId = $attributes['parent_id'] ? (int) $attributes['parent_id'] : null;
            if ($parentId === $category->id) {
                throw new RuntimeException('A category cannot be its own parent.');
            }
            $data['parent_id'] = $parentId;
        }
        if (array_key_exists('description', $attributes)) {
            $data['description'] = $attributes['description'];
        }

        if (! empty($data)) {
            $category->update($data);
        }

        return $category->fresh();
    }

    /**
     * Delete a category.
     */
    public function delete(int | string $entityIdOrSlug, int | string $categoryIdOrSlug, ?int $tenantId = null): bool
    {
        $category = $this->findOrFail($entityIdOrSlug, $categoryIdOrSlug, $tenantId);

        // Detach records from this category
        $category->records()->detach();

        return (bool) $category->delete();
    }
}
