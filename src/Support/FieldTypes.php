<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Carbon\Carbon;
use IvanMercedes\FlexFields\Models\CustomField;

/**
 * Central knowledge about field types: how values are cast, stored and which
 * capabilities (translations, options, relations...) each type supports.
 */
class FieldTypes
{
    /** Types that can be marked as translatable. */
    public const TRANSLATABLE = ['text', 'textarea', 'richtext'];

    /** Types whose stored value is a JSON array/object. */
    public const STRUCTURED = ['json', 'multiselect', 'tags', 'repeater', 'address'];

    /** Types that use the "options" list. */
    public const WITH_OPTIONS = ['select', 'multiselect'];

    /** Sub-fields of the structured "address" type. */
    public const ADDRESS_PARTS = ['street', 'street_2', 'city', 'state', 'postal_code', 'country'];

    /** Field types shipped with FlexFields (type => translation key). */
    public const BUILT_IN = [
        'text', 'textarea', 'number', 'email', 'url', 'date', 'datetime', 'boolean', 'select',
        'multiselect', 'color', 'file', 'image', 'richtext', 'json', 'tags', 'repeater',
        'phone', 'address', 'relationship',
    ];

    /**
     * Type => label map. Built-in types are always available (even with a
     * config file published before they existed); config entries can relabel
     * them or add custom types (e.g. handled by DynamicFormBuilder macros).
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        $builtIn = [];

        foreach (self::BUILT_IN as $type) {
            $builtIn[$type] = "flex-fields::flex-fields.field_types.{$type}";
        }

        return array_merge($builtIn, (array) config('flex-fields.field_types', []));
    }

    /**
     * Translated options for selects.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return Label::options(self::definitions());
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }

    public static function isTranslatable(CustomField $field): bool
    {
        return ! empty($field->settings['translatable']) && in_array($field->type, self::TRANSLATABLE, true);
    }

    public static function isMultiple(CustomField $field): bool
    {
        return ! empty($field->settings['multiple']);
    }

    /**
     * @return array<int, string>
     */
    public static function locales(): array
    {
        $locales = array_values(array_filter((array) config('flex-fields.translations.locales', ['en'])));

        return $locales ?: [config('app.locale', 'en')];
    }

    public static function fallbackLocale(): string
    {
        return (string) (config('flex-fields.translations.fallback_locale') ?: self::locales()[0]);
    }

    /**
     * Cast a raw stored value to its PHP representation for the given field.
     */
    public static function cast(?CustomField $field, mixed $raw): mixed
    {
        $type = $field?->type ?? 'text';
        $settings = $field?->settings ?? [];

        if ($field && self::isTranslatable($field)) {
            return self::decodeTranslations($raw);
        }

        return match ($type) {
            'number' => is_numeric($raw) ? (float) $raw : null,
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'date' => $raw ? Carbon::parse($raw)->toDateString() : null,
            'datetime' => $raw ? Carbon::parse($raw)->toDateTimeString() : null,
            'repeater' => self::normalizeRepeaterItems(is_string($raw) ? json_decode($raw, true) : $raw),
            'json', 'multiselect', 'tags', 'address' => is_string($raw) ? json_decode($raw, true) : $raw,
            'relationship' => self::castRelationship($raw, ! empty($settings['multiple'])),
            'image', 'file' => match (true) {
                ! empty($settings['multiple']) => is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw,
                (is_string($raw) && (str_starts_with($raw, '{') || str_starts_with($raw, '['))) => (array_values(json_decode($raw, true) ?? [])[0] ?? $raw),
                default => $raw,
            },
            default => $raw,
        };
    }

    /**
     * Serialize a PHP value to the raw string stored in ff_field_values.
     */
    public static function serialize(mixed $value): mixed
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value;
    }

    /**
     * PHP cast type name used by CustomField::getFieldCastType().
     */
    public static function castType(CustomField $field): string
    {
        if (self::isTranslatable($field)) {
            return 'array';
        }

        if (in_array($field->type, ['image', 'file', 'relationship'], true) && self::isMultiple($field)) {
            return 'array';
        }

        return match ($field->type) {
            'number' => 'float',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'datetime',
            'relationship' => 'integer',
            'json', 'multiselect', 'tags', 'repeater', 'address' => 'array',
            default => 'string',
        };
    }

    /**
     * Older versions stored repeater items from the admin form with "ff_"
     * prefixed sub-field keys; expose them with the declared keys.
     */
    protected static function normalizeRepeaterItems(mixed $items): mixed
    {
        if (! is_array($items)) {
            return $items;
        }

        return array_map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }

            foreach ($item as $key => $value) {
                if (is_string($key) && str_starts_with($key, 'ff_') && ! array_key_exists(substr($key, 3), $item)) {
                    $item[substr($key, 3)] = $value;
                    unset($item[$key]);
                }
            }

            return $item;
        }, $items);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function decodeTranslations(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Legacy plain value stored before the field became translatable.
        return [self::fallbackLocale() => $raw];
    }

    protected static function castRelationship(mixed $raw, bool $multiple): int | array | null
    {
        if ($raw === null || $raw === '') {
            return $multiple ? [] : null;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        $ids = array_values(array_map('intval', array_filter((array) ($decoded ?? $raw), 'is_numeric')));

        if ($multiple) {
            return $ids;
        }

        return $ids[0] ?? null;
    }
}
