<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\EntityRecord;

/**
 * Absolute URLs of the files stored in a record's image and file fields.
 *
 * Field values keep their disk paths (what clients send back when writing);
 * this map mirrors their shape with public URLs, so frontends can render media
 * without knowing where the files are stored (local disk, S3, R2...).
 * Translatable fields keep their locales and repeaters only their media
 * sub-fields. Fields stored in Spatie Media Library already hold URLs.
 */
class MediaUrls
{
    public const TYPES = ['image', 'file'];

    /**
     * Media URLs keyed by field key.
     *
     * @return array<string, mixed>
     */
    public static function for(EntityRecord $record): array
    {
        $values = $record->data;
        $media = MediaLibrary::urls($record);

        foreach (FieldCache::getForEntity($record->entity_id) as $field) {
            if (MediaLibrary::handles($field) || ! array_key_exists($field->key, $values)) {
                continue;
            }

            if (in_array($field->type, self::TYPES, true)) {
                $media[$field->key] = FieldTypes::isTranslatable($field)
                    ? static::perLocale($values[$field->key])
                    : static::urls($values[$field->key]);
            } elseif ($field->type === 'repeater' && ($items = static::repeaterMedia($field, $values[$field->key])) !== null) {
                $media[$field->key] = $items;
            }
        }

        return $media;
    }

    /**
     * Public URL of a stored path on the uploads disk. Absolute URLs are kept as-is.
     */
    public static function url(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        return Storage::disk(config('flex-fields.uploads.disk', 'public'))->url($path);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected static function repeaterMedia(CustomField $field, mixed $items): ?array
    {
        $subfields = array_filter(
            (array) ($field->settings['schema'] ?? []),
            fn (mixed $subfield): bool => is_array($subfield) && in_array($subfield['type'] ?? null, self::TYPES, true),
        );

        if ($subfields === [] || ! is_array($items)) {
            return null;
        }

        return array_values(array_map(
            fn (mixed $item): array => collect($subfields)
                ->mapWithKeys(fn (array $subfield): array => [$subfield['key'] => static::urls(is_array($item) ? ($item[$subfield['key']] ?? null) : null)])
                ->all(),
            $items,
        ));
    }

    protected static function perLocale(mixed $value): mixed
    {
        return is_array($value) && ! array_is_list($value)
            ? array_map(static::urls(...), $value)
            : static::urls($value);
    }

    protected static function urls(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static::url(...), $value)));
        }

        return static::url($value);
    }
}
