<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Illuminate\Support\Facades\DB;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityCategory;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Models\FieldGroup;
use IvanMercedes\FlexFields\Support\FlexSchema;
use RuntimeException;

/**
 * Exports content models (entities, categories, field groups, fields and,
 * optionally, records) as a portable array, and imports them into any tenant.
 *
 * References are kept by slug / key instead of database IDs, so a definition
 * can move between environments and tenants, live in a JSON file in version
 * control or seed new tenants from a template. Importing is idempotent: items
 * are matched by slug / key and updated, records are only created when their
 * slug does not exist yet.
 */
class SchemaTransfer
{
    public const VERSION = 1;

    /** Field attributes carried by a definition. */
    protected const FIELD_ATTRIBUTES = [
        'label', 'key', 'type', 'description', 'placeholder', 'default_value', 'options', 'validation_rules',
        'settings', 'order', 'is_required', 'is_active', 'is_searchable', 'is_shown_in_list', 'width',
    ];

    public function __construct(
        protected EntityDomainService $entities = new EntityDomainService,
        protected ?FieldDomainService $fields = null,
        protected ?CategoryDomainService $categories = null,
        protected ?FieldGroupDomainService $groups = null,
        protected ?RecordDomainService $records = null,
    ) {
        $this->fields ??= new FieldDomainService($this->entities);
        $this->categories ??= new CategoryDomainService($this->entities);
        $this->groups ??= new FieldGroupDomainService($this->entities);
        $this->records ??= new RecordDomainService($this->entities);
    }

    /**
     * @param  array<int, string>|null  $entitySlugs  null = every entity of the tenant
     * @return array{version: int, entities: array<int, array<string, mixed>>}
     */
    public function export(?array $entitySlugs = null, ?int $tenantId = null, bool $withRecords = false): array
    {
        $entities = $this->entities->list([], $tenantId)
            ->when($entitySlugs !== null, fn ($list) => $list->whereIn('slug', $entitySlugs))
            ->values();

        return [
            'version' => self::VERSION,
            'entities' => $entities->map(fn (Entity $entity) => $this->exportEntity($entity, $withRecords))->all(),
        ];
    }

    /**
     * Create or update everything in the definition, in one transaction.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, Entity> The imported entities.
     */
    public function import(array $definition, ?int $tenantId = null): array
    {
        $entities = array_values(array_filter((array) ($definition['entities'] ?? []), 'is_array'));

        return DB::transaction(function () use ($entities, $tenantId) {
            // Entities first, so relationship fields can point to any of them.
            $models = array_map(fn (array $entity) => $this->importEntity($entity, $tenantId), $entities);

            foreach ($entities as $index => $entity) {
                $this->importCategories($models[$index], (array) ($entity['categories'] ?? []), $tenantId);
                $this->importGroups($models[$index], (array) ($entity['groups'] ?? []), $tenantId);
                $this->importFields($models[$index], (array) ($entity['fields'] ?? []), $tenantId);
            }

            // Records last: relationship values reference records of any entity.
            $created = [];
            foreach ($entities as $index => $entity) {
                $created[$index] = $this->importRecords($models[$index], (array) ($entity['records'] ?? []), $tenantId);
            }

            foreach ($entities as $index => $entity) {
                $this->linkRelationships($models[$index], $created[$index], $tenantId);
            }

            return array_map(fn (Entity $entity) => $entity->fresh(), $models);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function exportEntity(Entity $entity, bool $withRecords): array
    {
        $categories = $entity->categories()->orderBy('id')->get();
        $categorySlugs = $categories->pluck('slug', 'id');

        $definition = [
            'name' => $entity->name,
            'slug' => $entity->slug,
            'description' => $entity->description,
            'icon' => $entity->icon,
            'color' => $entity->color,
            'is_active' => (bool) $entity->is_active,
            'show_in_menu' => (bool) $entity->show_in_menu,
            'menu_order' => (int) $entity->menu_order,
            'settings' => $entity->settings ?: [],
            'categories' => $categories->map(fn (EntityCategory $category) => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'parent' => $category->parent_id ? $categorySlugs->get($category->parent_id) : null,
            ])->all(),
            'groups' => FlexSchema::hasFieldGroups()
                ? $entity->fieldGroups()->orderBy('order')->get()->map(fn (FieldGroup $group) => [
                    'key' => $group->key,
                    'label' => $group->label,
                    'description' => $group->description,
                    'order' => $group->order,
                    'is_collapsible' => $group->is_collapsible,
                    'is_collapsed' => $group->is_collapsed,
                ])->all()
                : [],
            'fields' => $entity->customFields()->orderBy('order')->get()->map(fn (CustomField $field) => array_filter(
                [
                    ...$field->only(self::FIELD_ATTRIBUTES),
                    'group' => FlexSchema::hasFieldGroups() ? $field->fieldGroup?->key : null,
                    'categories' => $field->category_ids === null ? null : collect($field->category_ids)->map(fn ($id) => $categorySlugs->get($id))->filter()->values()->all(),
                ],
                fn (mixed $value) => $value !== null,
            ))->all(),
        ];

        if ($withRecords) {
            $definition['records'] = $this->exportRecords($entity);
        }

        return $definition;
    }

    /**
     * Records with relationship values as slugs of the related records.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function exportRecords(Entity $entity): array
    {
        $relationships = $entity->customFields()->where('type', 'relationship')->get();

        return $entity->records()->with(['categories', 'fieldValues.customField'])->orderBy('order')->orderBy('id')->get()
            ->map(function (EntityRecord $record) use ($relationships) {
                $values = $record->data;

                foreach ($relationships as $field) {
                    if (array_key_exists($field->key, $values)) {
                        $slugs = $record->related($field->key)->pluck('slug')->all();
                        $values[$field->key] = ! empty($field->settings['multiple']) ? $slugs : ($slugs[0] ?? null);
                    }
                }

                return [
                    'title' => $record->title,
                    'slug' => $record->slug,
                    'status' => $record->status,
                    'publish_at' => $record->publish_at?->toIso8601String(),
                    'order' => $record->order,
                    'categories' => $record->categories->pluck('slug')->all(),
                    'values' => $values,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function importEntity(array $definition, ?int $tenantId): Entity
    {
        $attributes = array_intersect_key($definition, array_flip([
            'name', 'slug', 'description', 'icon', 'color', 'is_active', 'show_in_menu', 'menu_order', 'settings',
        ]));

        if (empty($attributes['slug']) && empty($attributes['name'])) {
            throw new RuntimeException('Every imported entity needs a name or a slug.');
        }

        $existing = empty($attributes['slug']) ? null : $this->entities->find((string) $attributes['slug'], $tenantId);

        return $existing
            ? $this->entities->update($existing->id, $attributes, $tenantId)
            : $this->entities->create($attributes, $tenantId);
    }

    /**
     * @param  array<int, mixed>  $categories
     */
    protected function importCategories(Entity $entity, array $categories, ?int $tenantId): void
    {
        foreach (array_filter($categories, 'is_array') as $category) {
            $attributes = array_intersect_key($category, array_flip(['name', 'slug', 'description']));
            $existing = empty($category['slug']) ? null : $this->categories->find($entity->id, (string) $category['slug'], $tenantId);

            $existing
                ? $this->categories->update($entity->id, $existing->id, $attributes, $tenantId)
                : $this->categories->create($entity->id, $attributes, $tenantId);
        }

        // Parents once every category exists.
        foreach (array_filter($categories, fn ($category) => is_array($category) && ! empty($category['slug']) && ! empty($category['parent'])) as $category) {
            $parent = $this->categories->find($entity->id, (string) $category['parent'], $tenantId);

            $this->categories->findOrFail($entity->id, (string) $category['slug'], $tenantId)->update(['parent_id' => $parent?->id]);
        }
    }

    /**
     * @param  array<int, mixed>  $groups
     */
    protected function importGroups(Entity $entity, array $groups, ?int $tenantId): void
    {
        if (! FlexSchema::hasFieldGroups()) {
            return;
        }

        foreach (array_filter($groups, 'is_array') as $group) {
            $attributes = array_intersect_key($group, array_flip(['label', 'key', 'description', 'order', 'is_collapsible', 'is_collapsed']));
            $existing = empty($group['key']) ? null : $this->groups->find($entity->id, (string) $group['key'], $tenantId);

            $existing
                ? $this->groups->update($entity->id, $existing->id, $attributes, $tenantId)
                : $this->groups->create($entity->id, $attributes, $tenantId);
        }
    }

    /**
     * @param  array<int, mixed>  $fields
     */
    protected function importFields(Entity $entity, array $fields, ?int $tenantId): void
    {
        $fields = array_values(array_filter($fields, fn ($field) => is_array($field) && ! empty($field['label'])));

        // show_when conditions point to other fields: apply them once all exist.
        foreach ($fields as $field) {
            $attributes = array_intersect_key($field, array_flip([...self::FIELD_ATTRIBUTES, 'group']));
            $attributes['settings'] = array_diff_key((array) ($field['settings'] ?? []), ['show_when' => true]);

            if (array_key_exists('categories', $field)) {
                $attributes['category_ids'] = $field['categories'];
            }

            $existing = empty($field['key']) ? null : $this->fields->find($entity->id, (string) $field['key'], $tenantId);

            $existing
                ? $this->fields->update($entity->id, $existing->id, $attributes, $tenantId)
                : $this->fields->create($entity->id, $attributes, $tenantId);
        }

        foreach ($fields as $field) {
            if (! empty($field['key']) && ! empty($field['settings']['show_when'])) {
                $this->fields->update($entity->id, (string) $field['key'], ['settings' => ['show_when' => $field['settings']['show_when']]], $tenantId);
            }
        }
    }

    /**
     * Create the records whose slug does not exist yet. Relationship values
     * are left for linkRelationships().
     *
     * @param  array<int, mixed>  $records
     * @return array<int, array{record: EntityRecord, values: array<string, mixed>}>
     */
    protected function importRecords(Entity $entity, array $records, ?int $tenantId): array
    {
        $relationshipKeys = $entity->customFields()->where('type', 'relationship')->pluck('key')->all();
        $created = [];

        foreach (array_filter($records, 'is_array') as $record) {
            if (! empty($record['slug']) && $this->records->find($entity->id, (string) $record['slug'], $tenantId, withTrashed: true)) {
                continue;
            }

            $values = (array) ($record['values'] ?? []);

            $model = $this->records->create(
                $entity->id,
                array_intersect_key($record, array_flip(['title', 'slug', 'status', 'publish_at', 'order'])),
                array_diff_key($values, array_flip($relationshipKeys)),
                (array) ($record['categories'] ?? []),
                $tenantId,
            );

            $created[] = ['record' => $model, 'values' => array_intersect_key($values, array_flip($relationshipKeys))];
        }

        return $created;
    }

    /**
     * Resolve relationship values given as slugs (or IDs) of the related records.
     *
     * @param  array<int, array{record: EntityRecord, values: array<string, mixed>}>  $created
     */
    protected function linkRelationships(Entity $entity, array $created, ?int $tenantId): void
    {
        $fields = $entity->customFields()->where('type', 'relationship')->get()->keyBy('key');

        foreach ($created as ['record' => $record, 'values' => $values]) {
            $resolved = [];

            foreach ($values as $key => $value) {
                $related = (string) ($fields[$key]->settings['related_entity'] ?? '');
                $ids = array_values(array_filter(array_map(
                    fn ($reference) => $this->records->find($related, (string) $reference, $tenantId)?->id,
                    (array) $value,
                )));

                $resolved[$key] = is_array($value) ? $ids : ($ids[0] ?? null);
            }

            if ($resolved !== []) {
                $this->records->update($entity->id, $record->id, fieldValues: $resolved, tenantId: $tenantId);
            }
        }
    }
}
