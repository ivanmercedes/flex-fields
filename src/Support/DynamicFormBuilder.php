<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Filament\Facades\Filament;
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
                $disk = config('flex-fields.uploads.disk', 'public');
                $visibility = config('flex-fields.uploads.visibility', 'public');

                $component = Forms\Components\FileUpload::make($key)
                    ->label($field->label)
                    ->disk($disk)
                    ->visibility($visibility)
                    ->directory(self::resolveUploadDirectory($field));

                if (! empty($field->settings['multiple'])) {
                    $component->multiple()->reorderable();

                    if (method_exists($component, 'panelLayout')) {
                        $component->panelLayout('grid');
                    }
                    if (method_exists($component, 'grid')) {
                        $component->grid(3);
                    }
                }

                break;

            case 'image':
                $disk = config('flex-fields.uploads.disk', 'public');
                $visibility = config('flex-fields.uploads.visibility', 'public');

                $component = Forms\Components\FileUpload::make($key)
                    ->label($field->label)
                    ->image()
                    ->imageEditor()
                    ->disk($disk)
                    ->visibility($visibility)
                    ->directory(self::resolveUploadDirectory($field));

                if (! empty($field->settings['multiple'])) {
                    $component->multiple()->reorderable();

                    if (method_exists($component, 'panelLayout')) {
                        $component->panelLayout('grid');
                    }
                    if (method_exists($component, 'grid')) {
                        $component->grid(3);
                    }
                }

                if (! empty($field->settings['image_max_width']) && method_exists($component, 'imageResizeTargetWidth')) {
                    $component->imageResizeTargetWidth((string) $field->settings['image_max_width']);
                }

                if (! empty($field->settings['image_max_height']) && method_exists($component, 'imageResizeTargetHeight')) {
                    $component->imageResizeTargetHeight((string) $field->settings['image_max_height']);
                }

                if (! empty($field->settings['image_quality']) && method_exists($component, 'imageResizeQuality')) {
                    $component->imageResizeQuality((int) $field->settings['image_quality']);
                }

                if (! empty($field->settings['optimize']) || ! empty($field->settings['optimize_images'])) {
                    $component->saveUploadedFileUsing(function ($file) use ($field, $disk) {
                        $directory = self::resolveUploadDirectory($field);
                        $filename = $file->getClientOriginalName();

                        $path = $file->storeAs($directory, $filename, [
                            'disk' => $disk,
                            'visibility' => config('flex-fields.uploads.visibility', 'public'),
                        ]);

                        $format = strtolower((string) ($field->settings['image_format'] ?? 'webp'));
                        $maxWidth = ! empty($field->settings['image_max_width']) ? (int) $field->settings['image_max_width'] : 1920;
                        $maxHeight = ! empty($field->settings['image_max_height']) ? (int) $field->settings['image_max_height'] : null;
                        $quality = ! empty($field->settings['image_quality']) ? (int) $field->settings['image_quality'] : 75;

                        return ImageOptimizer::optimize(
                            disk: $disk,
                            relativePath: $path,
                            format: $format,
                            maxWidth: $maxWidth,
                            maxHeight: $maxHeight,
                            quality: $quality
                        );
                    });
                }

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
                        if ($field->relationLoaded('entity')) {
                            $subField->setRelation('entity', $field->entity);
                        } else {
                            $subField->entity_id = $field->entity_id;
                        }
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

    public static function resolveUploadDirectory(CustomField $field): string
    {
        $pattern = (string) config('flex-fields.uploads.directory_pattern', 'flex-fields/{tenant_slug}/{entity_slug}/{field_key}');

        $entity = $field->entity;
        $entityId = $entity?->id ? (string) $entity->id : '';
        $entitySlug = $entity?->slug ? (string) $entity->slug : 'entity';
        $fieldKey = $field->key ? (string) $field->key : 'field';
        $fieldLabel = Str::slug($field->label ? (string) $field->label : 'field');
        $year = date('Y');
        $month = date('m');

        // Resolve Tenant if active/available
        $tenant = null;
        if (class_exists(Filament::class) && Filament::hasTenancy()) {
            $tenant = Filament::getTenant();
        }

        $tenantKeyAttr = (string) config('flex-fields.uploads.tenant_key_attribute', 'slug');

        $tenantId = '';
        $tenantSlug = '';

        if ($tenant) {
            $tenantId = (string) ($tenant->getKey() ?? '');
            $rawSlug = $tenant->{$tenantKeyAttr} ?? $tenant->slug ?? $tenant->id ?? $tenantId;
            $tenantSlug = Str::slug((string) $rawSlug);
        }

        $tenantKey = $tenantSlug ?: $tenantId;

        $replacements = [
            '{tenant_id}' => $tenantId,
            '{tenant_slug}' => $tenantSlug,
            '{tenant_key}' => $tenantKey,
            '{entity_id}' => $entityId,
            '{entity_slug}' => $entitySlug,
            '{field_key}' => $fieldKey,
            '{field_label}' => $fieldLabel,
            '{year}' => $year,
            '{month}' => $month,
        ];

        $directory = strtr($pattern, $replacements);

        // Remove empty placeholder directory segments caused by empty replacements (e.g. no tenant)
        $directory = preg_replace('#/{2,}#', '/', $directory);
        $directory = trim((string) $directory, '/');

        return $directory ?: 'flex-fields/uploads';
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
