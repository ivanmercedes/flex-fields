<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Models\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Support\FieldCache;

/**
 * HasFlexFields — Trait to attach FlexFields functionality to any Eloquent model (Product, User, etc.).
 *
 * Example usage:
 *   class Product extends Model {
 *       use HasFlexFields;
 *       protected string $flexEntitySlug = 'products'; // optional
 *   }
 */
trait HasFlexFields
{
    /**
     * Get the entity slug associated with this model.
     */
    public function getFlexEntitySlug(): string
    {
        if (isset($this->flexEntitySlug) && ! empty($this->flexEntitySlug)) {
            return $this->flexEntitySlug;
        }

        return Str::kebab(class_basename(static::class));
    }

    /**
     * Get the FlexFields Entity definition for this model.
     */
    public function flexEntity(): ?Entity
    {
        return Entity::where('slug', $this->getFlexEntitySlug())->first();
    }

    /**
     * Get all active custom fields defined for this model's entity.
     *
     * @return Collection<int, CustomField>
     */
    public function getFlexFields(): Collection
    {
        $entity = $this->flexEntity();
        if (! $entity) {
            return new Collection;
        }

        return FieldCache::getForEntity($entity);
    }

    /**
     * Get or create the underlying EntityRecord for this model instance.
     */
    public function getOrCreateFlexRecord(): ?EntityRecord
    {
        $entity = $this->flexEntity();
        if (! $entity) {
            return null;
        }

        $recordKey = (string) ($this->getKey() ?? $this->id ?? 'model-' . uniqid());
        $title = (string) ($this->name ?? $this->title ?? $this->label ?? class_basename(static::class) . ' #' . $recordKey);
        $slug = Str::slug($this->getFlexEntitySlug() . '-' . $recordKey);

        return EntityRecord::firstOrCreate(
            [
                'entity_id' => $entity->id,
                'slug' => $slug,
            ],
            [
                'title' => $title,
                'status' => 'published',
                'meta' => [
                    'model_type' => static::class,
                    'model_id' => $this->getKey(),
                ],
            ]
        );
    }

    /**
     * Get the current underlying EntityRecord for this model instance, if it exists.
     */
    public function getFlexRecord(): ?EntityRecord
    {
        $entity = $this->flexEntity();
        if (! $entity) {
            return null;
        }

        $recordKey = (string) ($this->getKey() ?? $this->id ?? '');
        $slug = Str::slug($this->getFlexEntitySlug() . '-' . $recordKey);

        return EntityRecord::where('entity_id', $entity->id)
            ->where(function ($query) use ($slug) {
                $query->where('slug', $slug)
                    ->orWhere('meta->model_id', (string) $this->getKey());
            })->first();
    }

    /**
     * Get value of a flex field by key.
     */
    public function getFlexValue(string $key, mixed $default = null): mixed
    {
        $record = $this->getFlexRecord();
        if (! $record) {
            return $default;
        }

        $val = $record->getValue($key);

        return $val !== null ? $val : $default;
    }

    /**
     * Set value of a flex field by key.
     */
    public function setFlexValue(string $key, mixed $value): static
    {
        $record = $this->getOrCreateFlexRecord();
        if ($record) {
            $record->setValue($key, $value);
        }

        return $this;
    }

    /**
     * Get all flex field values as a flat key => value array.
     */
    public function getFlexData(): array
    {
        $record = $this->getFlexRecord();

        return $record ? $record->data : [];
    }

    /**
     * Sync multiple flex field values at once.
     */
    public function syncFlexValues(array $values): static
    {
        $record = $this->getOrCreateFlexRecord();
        if ($record) {
            foreach ($values as $key => $value) {
                $record->setValue($key, $value);
            }
        }

        return $this;
    }
}
