<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\EntityRecord;
use Spatie\MediaLibrary\HasMedia;

/**
 * Optional Spatie Media Library integration for "image" and "file" fields.
 *
 * Enabled with config('flex-fields.media.driver') = 'spatie', a record model
 * implementing HasMedia (MediaEntityRecord) and the
 * filament/spatie-laravel-media-library-plugin package.
 */
class MediaLibrary
{
    public static function enabled(): bool
    {
        return config('flex-fields.media.driver') === 'spatie'
            && interface_exists(HasMedia::class)
            && class_exists(SpatieMediaLibraryFileUpload::class)
            && is_a(FlexModels::record(), HasMedia::class, true);
    }

    /**
     * Whether the field's files are stored in the media library instead of field values.
     */
    public static function handles(CustomField $field): bool
    {
        return in_array($field->type, ['image', 'file'], true) && static::enabled();
    }

    public static function collection(CustomField $field): string
    {
        return config('flex-fields.media.collection_prefix', 'ff_') . $field->key;
    }

    /**
     * Build the Filament upload component for a media-library backed field.
     */
    public static function component(CustomField $field, string $statePath): SpatieMediaLibraryFileUpload
    {
        $component = SpatieMediaLibraryFileUpload::make($statePath)
            ->label($field->label)
            ->collection(static::collection($field))
            ->disk(config('flex-fields.uploads.disk', 'public'))
            ->visibility(config('flex-fields.uploads.visibility', 'public'));

        if ($field->type === 'image') {
            $component->image()->imageEditor();
        }

        if (FieldTypes::isMultiple($field)) {
            $component->multiple()->reorderable();
        }

        return $component;
    }

    /**
     * Media URLs keyed by field key for every media-library backed field.
     *
     * @return array<string, mixed>
     */
    public static function urls(EntityRecord $record): array
    {
        if (! static::enabled() || ! $record instanceof HasMedia) {
            return [];
        }

        $urls = [];

        foreach ($record->entity?->customFields ?? [] as $field) {
            if (! static::handles($field)) {
                continue;
            }

            $items = $record->getMedia(static::collection($field))->map(fn ($media) => $media->getUrl())->values()->all();
            $urls[$field->key] = FieldTypes::isMultiple($field) ? $items : ($items[0] ?? null);
        }

        return $urls;
    }
}
