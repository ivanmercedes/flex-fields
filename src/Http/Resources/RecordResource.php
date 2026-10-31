<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Http\Resources;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Support\FieldCache;
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\MediaUrls;
use stdClass;

/**
 * JSON representation of a record for frontend / mobile consumers.
 *
 * Field values are exposed under "values" and the public URLs of their files
 * under "media". Query options:
 *   ?locale=es                  translatable values resolved to one locale (with fallback)
 *   ?fields=price,cover         only these field keys in "values" / "media"
 *   ?include=relations          expand relationship fields (id, title, slug, status)
 *   ?include=relations.values   ...including the related records' values
 *
 * @property EntityRecord $resource
 */
class RecordResource extends JsonResource
{
    /**
     * @var array<int, Closure(array<string, mixed>, EntityRecord, Request): array<string, mixed>>
     */
    protected static array $extensions = [];

    /**
     * Add or change keys of every record representation, e.g.:
     *   RecordResource::extend(fn (array $data, EntityRecord $record, Request $request) => $data + ['url' => ...]);
     *
     * @param  Closure(array<string, mixed>, EntityRecord, Request): array<string, mixed>  $callback
     */
    public static function extend(Closure $callback): void
    {
        static::$extensions[] = $callback;
    }

    public static function flushExtensions(): void
    {
        static::$extensions = [];
    }

    /**
     * The requested locale, when it is one of the configured translation locales.
     */
    public static function requestedLocale(Request $request): ?string
    {
        $locale = $request->query('locale');

        return is_string($locale) && in_array($locale, FieldTypes::locales(), true) ? $locale : null;
    }

    /**
     * @return array<int, string>|null
     */
    public static function requestedFields(Request $request): ?array
    {
        $fields = $request->query('fields');

        if (! is_string($fields) || trim($fields) === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $fields))));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = app(RecordDomainService::class)->formatRecord($this->resource);
        $locale = static::requestedLocale($request);
        $fields = static::requestedFields($request);

        // Field values are exposed as "values" (same key accepted by POST/PUT).
        $data = array_merge(
            array_diff_key($data, ['data' => true, 'created_at' => true, 'updated_at' => true, 'deleted_at' => true]),
            [
                'values' => $this->present($this->resource, $data['data'], $locale, $fields),
                'media' => $this->present($this->resource, MediaUrls::for($this->resource), $locale, $fields) ?: new stdClass,
            ],
            array_intersect_key($data, array_flip(['created_at', 'updated_at', 'deleted_at'])),
        );

        $includes = explode(',', (string) $request->query('include', ''));

        if (array_intersect(['relations', 'relations.values'], $includes) !== []) {
            $data['relations'] = $this->relations(in_array('relations.values', $includes, true), $locale);
        }

        foreach (static::$extensions as $extension) {
            $data = $extension($data, $this->resource, $request);
        }

        return $data;
    }

    protected static function translate(mixed $value, string $locale): mixed
    {
        if (! is_array($value) || array_is_list($value)) {
            return $value;
        }

        if (array_key_exists($locale, $value) && $value[$locale] !== null && $value[$locale] !== '') {
            return $value[$locale];
        }

        return $value[FieldTypes::fallbackLocale()] ?? null;
    }

    /**
     * Keep the requested fields and resolve translatable values to the locale.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>|null  $fields
     * @return array<string, mixed>
     */
    protected function present(EntityRecord $record, array $values, ?string $locale, ?array $fields): array
    {
        if ($fields !== null) {
            $values = array_intersect_key($values, array_flip($fields));
        }

        if ($locale === null) {
            return $values;
        }

        foreach (FieldCache::getForEntity($record->entity_id) as $field) {
            if (array_key_exists($field->key, $values) && FieldTypes::isTranslatable($field)) {
                $values[$field->key] = static::translate($values[$field->key], $locale);
            }
        }

        return $values;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function relations(bool $withValues, ?string $locale): array
    {
        $relations = [];

        foreach ($this->resource->entity->customFields->where('type', 'relationship') as $field) {
            $relations[$field->key] = $this->resource->related($field->key)
                ->map(function (EntityRecord $record) use ($withValues, $locale) {
                    $related = [
                        'id' => $record->id,
                        'title' => $record->title,
                        'slug' => $record->slug,
                        'status' => $record->status,
                    ];

                    if ($withValues) {
                        $related['values'] = $this->present($record, $record->data, $locale, null);
                        $related['media'] = $this->present($record, MediaUrls::for($record), $locale, null) ?: new stdClass;
                    }

                    return $related;
                })
                ->values()
                ->all();
        }

        return $relations;
    }
}
