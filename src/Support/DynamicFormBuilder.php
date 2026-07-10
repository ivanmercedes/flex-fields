<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Filament\Forms;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;

class DynamicFormBuilder
{
    /**
     * @return SchemaComponent[]
     */
    public static function build(Entity $entity): array
    {
        $fields = $entity->customFields()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        if ($fields->isEmpty()) {
            return [
                EmptyState::make('')
                    ->description(Label::trans('flex-fields::flex-fields.record.helpers.empty_fields'))
                    ->icon(Heroicon::ArchiveBox)
                    ->columnSpanFull(),
            ];
        }

        return [
            Section::make($entity->name . ' ' . Label::trans('flex-fields::flex-fields.record.sections.fields_suffix'))
                ->description($entity->description)
                ->schema(self::buildFieldComponents($fields->all()))
                ->columnSpanFull()
                ->columns(12),
        ];
    }

    /**
     * @param  CustomField[]  $fields
     * @return SchemaComponent[]
     */
    public static function buildFieldComponents(array $fields): array
    {
        $components = [];

        foreach ($fields as $field) {
            $component = self::makeComponent($field);

            if ($component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    public static function makeComponent(CustomField $field): ?SchemaComponent
    {
        $key = 'ff_' . $field->key;
        $colSpan = self::resolveColumnSpan($field->width);
        $component = null;

        switch ($field->type) {
            case 'text':
                $component = Forms\Components\TextInput::make($key)
                    ->label($field->label)
                    ->placeholder($field->placeholder ?? '')
                    ->default($field->default_value)
                    ->maxLength(255);

                break;

            case 'textarea':
                $component = Forms\Components\Textarea::make($key)
                    ->label($field->label)
                    ->placeholder($field->placeholder ?? '')
                    ->default($field->default_value)
                    ->rows(4);

                break;

            case 'number':
                $component = Forms\Components\TextInput::make($key)
                    ->label($field->label)
                    ->placeholder($field->placeholder ?? '')
                    ->default($field->default_value)
                    ->numeric();

                break;

            case 'email':
                $component = Forms\Components\TextInput::make($key)
                    ->label($field->label)
                    ->placeholder($field->placeholder ?? 'email@example.com')
                    ->default($field->default_value)
                    ->email();

                break;

            case 'url':
                $component = Forms\Components\TextInput::make($key)
                    ->label($field->label)
                    ->placeholder($field->placeholder ?? 'https://')
                    ->default($field->default_value)
                    ->url();

                break;

            case 'date':
                $component = Forms\Components\DatePicker::make($key)
                    ->label($field->label)
                    ->default($field->default_value);

                break;

            case 'datetime':
                $component = Forms\Components\DateTimePicker::make($key)
                    ->label($field->label)
                    ->default($field->default_value);

                break;

            case 'boolean':
                $component = Forms\Components\Toggle::make($key)
                    ->label($field->label)
                    ->default((bool) $field->default_value);

                break;

            case 'select':
                $component = Forms\Components\Select::make($key)
                    ->label($field->label)
                    ->options($field->parsed_options)
                    ->default($field->default_value)
                    ->searchable();

                break;

            case 'multiselect':
                $component = Forms\Components\Select::make($key)
                    ->label($field->label)
                    ->options($field->parsed_options)
                    ->default($field->default_value ? json_decode($field->default_value, true) : null)
                    ->multiple()
                    ->searchable();

                break;

            case 'color':
                $component = Forms\Components\ColorPicker::make($key)
                    ->label($field->label)
                    ->default($field->default_value);

                break;

            case 'file':
                $component = Forms\Components\FileUpload::make($key)
                    ->label($field->label)
                    ->directory('flex-fields/' . Str::slug($field->label));

                break;

            case 'image':
                $component = Forms\Components\FileUpload::make($key)
                    ->label($field->label)
                    ->image()
                    ->imageEditor()
                    ->directory('flex-fields/images');

                break;

            case 'richtext':
                $component = Forms\Components\RichEditor::make($key)
                    ->label($field->label)
                    ->default($field->default_value)
                    ->extraInputAttributes(['style' => 'min-height: 20rem; max-height: 50vh; overflow-y: auto;'])
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'strike',
                        'link',
                        'bulletList',
                        'orderedList',
                        'h2',
                        'h3',
                        'blockquote',
                        'codeBlock',
                    ]);

                break;

            case 'json':
                $component = Forms\Components\Textarea::make($key)
                    ->label($field->label)
                    ->placeholder(Label::trans('flex-fields::flex-fields.record.placeholders.json'))
                    ->rows(6)
                    ->helperText(Label::trans('flex-fields::flex-fields.record.helpers.json'));

                break;

            case 'tags':
                $component = Forms\Components\TagsInput::make($key)
                    ->label($field->label)
                    ->placeholder($field->placeholder ?? Label::trans('flex-fields::flex-fields.record.placeholders.tags'));

                break;

            case 'repeater':
                $schema = [];
                $repeaterFields = $field->settings['schema'] ?? [];

                if (is_array($repeaterFields) && count($repeaterFields) > 0) {
                    foreach ($repeaterFields as $subFieldData) {
                        // We instantiate a temporary CustomField in memory
                        // to reuse all the rich field types inside the repeater
                        $subField = new CustomField($subFieldData);
                        $subFieldComponent = self::makeComponent($subField);
                        if ($subFieldComponent) {
                            $schema[] = $subFieldComponent;
                        }
                    }
                } else {
                    // Fallback if no schema is defined
                    $schema[] = Forms\Components\TextInput::make('value')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.option_value'))
                        ->required();
                }

                $component = Forms\Components\Repeater::make($key)
                    ->label($field->label)
                    ->schema($schema)
                    ->columns(12)
                    ->default($field->default_value ? json_decode($field->default_value, true) : null)
                    ->reorderable()
                    ->collapsible();

                break;

            default:
                $component = Forms\Components\TextInput::make($key)
                    ->label($field->label);
        }

        if (! $component) {
            return null;
        }

        if ($field->is_required) {
            $component->required();
        }

        if ($field->description) {
            $component->helperText($field->description);
        }

        $component->columnSpan($colSpan);

        return $component;
    }

    protected static function resolveColumnSpan(string $width): int
    {
        return match ($width) {
            'half' => 6,
            'third' => 4,
            default => 12,
        };
    }
}
