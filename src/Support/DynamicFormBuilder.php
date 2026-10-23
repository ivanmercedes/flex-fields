<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\FieldGroup;
use IvanMercedes\FlexFields\Services\RecordValueValidator;

class DynamicFormBuilder
{
    use Macroable;

    /**
     * @return SchemaComponent[]
     */
    public static function build(Entity $entity): array
    {
        $fields = FieldCache::getForEntity($entity);

        if ($fields->isEmpty()) {
            return [
                EmptyState::make('')
                    ->description(Label::trans('flex-fields::flex-fields.record.helpers.empty_fields'))
                    ->icon(Heroicon::ArchiveBox)
                    ->columnSpanFull(),
            ];
        }

        $allFields = $fields->all();
        $controllingKeys = self::controllingKeys($allFields);
        $groups = FlexSchema::hasFieldGroups() ? $entity->fieldGroups()->get() : collect();
        $groupIds = $groups->pluck('id')->all();

        $sections = [];

        $ungrouped = array_values(array_filter(
            $allFields,
            fn (CustomField $field) => $field->field_group_id === null || ! in_array($field->field_group_id, $groupIds),
        ));

        if ($ungrouped !== []) {
            $sections[] = self::hideOutsideCategories(
                Section::make($entity->name . ' ' . Label::trans('flex-fields::flex-fields.record.sections.fields_suffix'))
                    ->description($entity->description)
                    ->schema(self::buildFieldComponents($ungrouped, $controllingKeys))
                    ->columnSpanFull()
                    ->columns(12),
                $ungrouped,
            );
        }

        foreach ($groups as $group) {
            $groupFields = array_values(array_filter(
                $allFields,
                fn (CustomField $field) => $field->field_group_id === $group->id,
            ));

            if ($groupFields === []) {
                continue;
            }

            $sections[] = self::makeGroupSection($group, $groupFields, $controllingKeys);
        }

        return $sections;
    }

    /**
     * @param  CustomField[]  $fields
     * @param  array<int, string>  $controllingKeys
     */
    public static function makeGroupSection(FieldGroup $group, array $fields, array $controllingKeys = []): Section
    {
        return self::hideOutsideCategories(
            Section::make($group->label)
                ->description($group->description)
                ->collapsible((bool) $group->is_collapsible)
                ->collapsed((bool) $group->is_collapsible && (bool) $group->is_collapsed)
                ->schema(self::buildFieldComponents($fields, $controllingKeys))
                ->columnSpanFull()
                ->columns(12),
            $fields,
        );
    }

    /**
     * Categories a section belongs to: the union of its fields' categories, or [] when any
     * field is shown in every category (the section is then always visible).
     *
     * @param  CustomField[]  $fields
     * @return array<int, int>
     */
    public static function sectionCategoryIds(array $fields): array
    {
        $ids = [];

        foreach ($fields as $field) {
            if (empty($field->category_ids)) {
                return [];
            }

            $ids = array_merge($ids, array_map('intval', $field->category_ids));
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  CustomField[]  $fields
     * @param  array<int, string>|null  $controllingKeys  keys of fields other fields depend on (made live)
     * @return SchemaComponent[]
     */
    public static function buildFieldComponents(array $fields, ?array $controllingKeys = null): array
    {
        $controllingKeys ??= self::controllingKeys($fields);
        $components = [];

        foreach ($fields as $field) {
            $component = self::makeComponent($field);

            if (! $component) {
                continue;
            }

            if (in_array($field->key, $controllingKeys, true) && method_exists($component, 'live')) {
                $component->live();
            }

            $components[] = $component;
        }

        return $components;
    }

    /**
     * Keys of fields referenced by other fields' show_when rules.
     *
     * @param  CustomField[]  $fields
     * @return array<int, string>
     */
    public static function controllingKeys(array $fields): array
    {
        $keys = [];

        foreach ($fields as $field) {
            $keys = [...$keys, ...ConditionEvaluator::controllingKeys($field->settings['show_when'] ?? null)];
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  string  $statePrefix  "ff_" for record fields, "" for repeater sub-fields
     *                               (their items are stored with the plain sub-field keys).
     */
    public static function makeComponent(CustomField $field, string $statePrefix = 'ff_'): ?SchemaComponent
    {
        $key = $statePrefix . $field->key;
        $colSpan = self::resolveColumnSpan($field->width);
        $component = null;

        if (static::hasMacro($field->type)) {
            $component = static::callMacro($field->type, [$field]);
        } elseif (FieldTypes::isTranslatable($field)) {
            $component = self::makeTranslatableComponent($field);
        } elseif (MediaLibrary::handles($field)) {
            $component = MediaLibrary::component($field, $key);
        } else {
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
                            $subFieldComponent = self::makeComponent($subField, statePrefix: '');
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

                case 'phone':
                    $component = Forms\Components\TextInput::make($key)
                        ->label($field->label)
                        ->placeholder($field->placeholder ?? '')
                        ->default($field->default_value)
                        ->tel()
                        ->maxLength(50);

                    if (! empty($field->settings['phone_mask'])) {
                        $component->mask((string) $field->settings['phone_mask']);
                    }

                    break;

                case 'address':
                    $component = self::makeAddressComponent($field, $key);

                    break;

                case 'link':
                    $component = self::makeLinkComponent($field, $key);

                    break;

                case 'relationship':
                    $component = Forms\Components\Select::make($key)
                        ->label($field->label)
                        ->options(fn () => self::relationshipOptions($field))
                        ->multiple(FieldTypes::isMultiple($field))
                        ->searchable()
                        ->preload();

                    break;

                default:
                    $component = Forms\Components\TextInput::make($key)
                        ->label($field->label);
            }
        }

        if (! $component) {

            return null;
        }

        if ($field->is_required && method_exists($component, 'required')) {
            $component->required();
        }

        if ($field->description && method_exists($component, 'helperText')) {
            $component->helperText($field->description);
        }

        $rules = ValidationRules::forField($field->validation_rules);
        if ($rules !== [] && method_exists($component, 'rules')) {
            $component->rules($rules);
        }

        $showWhen = $field->settings['show_when'] ?? null;
        $categoryIds = $field->category_ids ?? [];

        if (! empty($categoryIds) || ! empty($showWhen)) {
            $component->hidden(function (Get $get) use ($categoryIds, $showWhen) {
                // Hide if none of the field's categories are selected on the record
                if (! empty($categoryIds) && count(array_intersect($categoryIds, (array) $get('categories'))) === 0) {
                    return true;
                }

                return ! empty($showWhen) && ! ConditionEvaluator::passes($showWhen, fn (string $key) => $get('ff_' . $key));
            });
        }

        $component->columnSpan($colSpan);

        return $component;
    }

    /**
     * One input per configured locale, rendered as tabs. State: ff_{key}.{locale}.
     */
    public static function makeTranslatableComponent(CustomField $field): Tabs
    {
        $tabs = [];

        foreach (FieldTypes::locales() as $locale) {
            $localized = $field->replicate();
            $localized->key = $field->key . '.' . $locale;
            $localized->label = $field->label . ' (' . strtoupper($locale) . ')';
            $localized->width = 'full';
            $localized->category_ids = null;
            $localized->is_required = $field->is_required && $locale === FieldTypes::fallbackLocale();
            $localized->settings = array_merge($field->settings ?? [], ['translatable' => false, 'show_when' => null]);
            $localized->setRelation('entity', $field->entity);

            $tabs[] = Tab::make(strtoupper($locale))
                ->schema(array_filter([self::makeComponent($localized)]))
                ->columns(12);
        }

        return Tabs::make($field->label)->tabs($tabs);
    }

    /**
     * Structured address sub-form stored as a JSON object.
     */
    public static function makeAddressComponent(CustomField $field, string $key): Fieldset
    {
        $parts = (new RecordValueValidator)->addressParts($field);
        $requiredParts = ['street', 'city', 'country'];

        $inputs = array_map(function (string $part) use ($field, $requiredParts) {
            $input = Forms\Components\TextInput::make($part)
                ->label(Label::trans("flex-fields::flex-fields.address.{$part}", default: str_replace('_', ' ', ucfirst($part))))
                ->maxLength(255)
                ->columnSpan(in_array($part, ['street', 'street_2'], true) ? 12 : 6);

            if ($field->is_required && in_array($part, $requiredParts, true)) {
                $input->required();
            }

            return $input;
        }, $parts);

        return Fieldset::make($field->label)
            ->statePath($key)
            ->columns(12)
            ->schema($inputs);
    }

    /**
     * Link sub-form (URL, title and target) stored as a JSON object.
     */
    public static function makeLinkComponent(CustomField $field, string $key): Fieldset
    {
        return Fieldset::make($field->label)
            ->statePath($key)
            ->columns(12)
            ->schema([
                Forms\Components\TextInput::make('url')
                    ->label(Label::trans('flex-fields::flex-fields.link.url', default: 'URL'))
                    ->required($field->is_required)
                    ->maxLength(2048)
                    ->columnSpan(5),
                Forms\Components\TextInput::make('title')
                    ->label(Label::trans('flex-fields::flex-fields.link.title', default: 'Title'))
                    ->maxLength(255)
                    ->columnSpan(4),
                Forms\Components\Select::make('target')
                    ->label(Label::trans('flex-fields::flex-fields.link.target', default: 'Open in'))
                    ->options(collect(FieldTypes::LINK_TARGETS)->mapWithKeys(fn (string $target) => [
                        $target => Label::trans("flex-fields::flex-fields.link.targets.{$target}", default: $target),
                    ])->all())
                    ->default('_self')
                    ->selectablePlaceholder(false)
                    ->columnSpan(3),
            ]);
    }

    /**
     * Options for a relationship field: records of the related entity (same tenant).
     *
     * @return array<int, string>
     */
    public static function relationshipOptions(CustomField $field): array
    {
        $relatedSlug = $field->settings['related_entity'] ?? null;

        if (! $relatedSlug) {
            return [];
        }

        $tenantId = $field->entity?->tenant_id ?? $field->tenant_id;

        $related = Entity::query()
            ->where('slug', $relatedSlug)
            ->when(
                $tenantId !== null,
                fn ($q) => $q->where('tenant_id', $tenantId),
                fn ($q) => config('flex-fields.tenancy.enabled', false) ? $q->whereNull('tenant_id') : $q,
            )
            ->first();

        if (! $related) {
            return [];
        }

        return $related->records()
            ->orderBy('title')
            ->limit(500)
            ->get(['id', 'title'])
            ->mapWithKeys(fn ($record) => [$record->id => $record->title ?: '#' . $record->id])
            ->all();
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

    /**
     * Hide the whole section when none of its fields apply to the record's categories,
     * instead of showing an empty section.
     *
     * @param  CustomField[]  $fields
     */
    private static function hideOutsideCategories(Section $section, array $fields): Section
    {
        $categoryIds = self::sectionCategoryIds($fields);

        if ($categoryIds === []) {
            return $section;
        }

        return $section->hidden(
            fn (Get $get) => count(array_intersect($categoryIds, array_map('intval', (array) $get('categories')))) === 0,
        );
    }
}
