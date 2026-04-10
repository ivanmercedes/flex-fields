<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Filament\Tables;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;

/**
 * DynamicTableBuilder
 *
 * Converts CustomField definitions into Filament table columns.
 * Only fields marked as `is_shown_in_list` are rendered.
 */
class DynamicTableBuilder
{
    /**
     * Build table columns for an entity's custom fields.
     *
     * @return Tables\Columns\Column[]
     */
    public static function build(?Entity $entity): array
    {
        if (! $entity) {
            return [];
        }

        $fields = $entity->customFields()
            ->where('is_active', true)
            ->where('is_shown_in_list', true)
            ->orderBy('order')
            ->get();

        $columns = [];

        foreach ($fields as $field) {
            $column = self::makeColumn($field);
            if ($column) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Create a Filament table column for a given CustomField.
     */
    public static function makeColumn(CustomField $field): ?Tables\Columns\Column
    {
        $column = null;

        switch ($field->type) {
            case 'boolean':
                $column = Tables\Columns\IconColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->getValue($field->key));

                break;

            case 'color':
                $column = Tables\Columns\ColorColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->getStateUsing(fn ($record) => $record->getValue($field->key));

                break;

            case 'image':
                $column = Tables\Columns\ImageColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->getStateUsing(fn ($record) => $record->getValue($field->key));

                break;

            case 'select':
            case 'multiselect':
                $options = $field->parsed_options;
                $column = Tables\Columns\TextColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->badge()
                    ->getStateUsing(function ($record) use ($field, $options) {
                        $val = $record->getValue($field->key);
                        if (is_array($val)) {
                            return implode(', ', array_map(fn ($v) => $options[$v] ?? $v, $val));
                        }

                        return $options[$val] ?? $val;
                    });

                break;

            case 'tags':
                $column = Tables\Columns\TextColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->badge()
                    ->separator(',')
                    ->getStateUsing(function ($record) use ($field) {
                        $val = $record->getValue($field->key);

                        return is_array($val) ? implode(',', $val) : $val;
                    });

                break;

            case 'date':
                $column = Tables\Columns\TextColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->date()
                    ->getStateUsing(fn ($record) => $record->getValue($field->key));

                break;

            case 'datetime':
                $column = Tables\Columns\TextColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->dateTime()
                    ->getStateUsing(fn ($record) => $record->getValue($field->key));

                break;

            case 'richtext':
                $column = Tables\Columns\TextColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->html()
                    ->limit(80)
                    ->getStateUsing(fn ($record) => $record->getValue($field->key));

                break;

            default:
                $column = Tables\Columns\TextColumn::make('field_' . $field->key)
                    ->label($field->label)
                    ->limit(60)
                    ->tooltip(fn (Tables\Columns\TextColumn $col) => strlen($col->getState()) > 60 ? $col->getState() : null)
                    ->searchable($field->is_searchable)
                    ->getStateUsing(fn ($record) => $record->getValue($field->key));

                break;
        }

        return $column;
    }
}
