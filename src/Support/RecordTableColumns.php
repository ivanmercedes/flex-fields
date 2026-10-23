<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;

/**
 * Table columns for the custom fields marked as "Show in table" (is_shown_in_list).
 */
class RecordTableColumns
{
    /**
     * @return array<int, Column>
     */
    public static function make(?Entity $entity): array
    {
        if (! $entity) {
            return [];
        }

        return FieldCache::getForEntity($entity)
            ->filter(fn (CustomField $field) => $field->is_shown_in_list)
            ->map(fn (CustomField $field) => static::column($field))
            ->values()
            ->all();
    }

    public static function column(CustomField $field): Column
    {
        $name = 'ff_' . $field->key;

        $column = match ($field->type) {
            'boolean' => IconColumn::make($name)
                ->boolean()
                ->state(fn (EntityRecord $record) => (bool) $record->getValue($field->key)),
            'image' => ImageColumn::make($name)
                ->disk(config('flex-fields.uploads.disk', 'public'))
                ->imageHeight(40)
                ->state(fn (EntityRecord $record) => static::firstPath($record->getValue($field->key))),
            'color' => ColorColumn::make($name)
                ->state(fn (EntityRecord $record) => $record->getValue($field->key)),
            default => TextColumn::make($name)
                ->state(fn (EntityRecord $record) => static::display($record, $field))
                ->limit(50)
                ->placeholder('—'),
        };

        $column->label($field->label)->toggleable();

        if ($field->is_searchable && $column instanceof TextColumn) {
            $column->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas(
                'fieldValues',
                fn (Builder $values) => $values->where('custom_field_id', $field->id)->where('value', 'like', "%{$search}%"),
            ));
        }

        return $column;
    }

    /**
     * Human-readable value of a field for tables and listings.
     */
    public static function display(EntityRecord $record, CustomField $field): ?string
    {
        if (FieldTypes::isTranslatable($field)) {
            $value = $record->getTranslation($field->key);

            return $value === null ? null : strip_tags((string) $value);
        }

        $value = $record->getValue($field->key);

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($field->type) {
            'select' => (string) ($field->parsed_options[$value] ?? $value),
            'multiselect' => implode(', ', array_map(fn ($v) => (string) ($field->parsed_options[$v] ?? $v), (array) $value)),
            'tags' => implode(', ', (array) $value),
            'relationship' => $record->related($field->key)->pluck('title')->implode(', '),
            'address' => implode(', ', array_filter((array) $value)),
            'link' => (string) (($value['title'] ?? '') ?: ($value['url'] ?? '')),
            'repeater', 'json' => count((array) $value) . ' ' . (count((array) $value) === 1 ? 'item' : 'items'),
            'richtext' => strip_tags((string) $value),
            'file' => static::firstPath($value),
            default => is_array($value) ? implode(', ', $value) : (string) $value,
        };
    }

    protected static function firstPath(mixed $value): ?string
    {
        return is_array($value) ? (array_values($value)[0] ?? null) : $value;
    }
}
