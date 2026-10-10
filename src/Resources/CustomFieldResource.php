<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityCategory;
use IvanMercedes\FlexFields\Models\FieldGroup;
use IvanMercedes\FlexFields\Resources\Concerns\AuthorizesFlexStructure;
use IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages;
use IvanMercedes\FlexFields\Services\FieldGroupDomainService;
use IvanMercedes\FlexFields\Support\ConditionEvaluator;
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\FlexSchema;
use IvanMercedes\FlexFields\Support\Keys;
use IvanMercedes\FlexFields\Support\Label;
use IvanMercedes\FlexFields\Support\ValidationRules;
use UnitEnum;

class CustomFieldResource extends Resource
{
    use AuthorizesFlexStructure;

    protected static ?string $model = CustomField::class;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-variable';

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'ff-custom-fields';

    public static function isScopedToTenant(): bool
    {
        return config('flex-fields.tenancy.enabled', false) && config('flex-fields.tenancy.is_tenant_aware.custom_fields', true);
    }

    public static function getTenantOwnershipRelationshipName(): string
    {
        return 'tenant';
    }

    public static function form(Schema $schema): Schema
    {
        $fieldTypes = FieldTypes::options();

        return $schema->columns(1)->components([
            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.identity'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('entity_id')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.entity'))
                        ->options(Entity::pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live(),

                    Forms\Components\Select::make('field_group_id')
                        ->label(Label::trans('flex-fields::flex-fields.field_group.label'))
                        ->helperText(Label::trans('flex-fields::flex-fields.field_group.helpers.select'))
                        ->options(fn (Get $get) => FieldGroup::where('entity_id', $get('entity_id'))->orderBy('order')->pluck('label', 'id'))
                        ->visible(fn (Get $get) => filled($get('entity_id')) && FlexSchema::hasFieldGroups())
                        ->searchable()
                        ->preload()
                        ->createOptionForm(FieldGroupForm::components())
                        ->createOptionUsing(fn (array $data, Get $get) => app(FieldGroupDomainService::class)
                            ->create((int) $get('entity_id'), $data)->id),

                    Forms\Components\Select::make('category_ids')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.categories', default: 'Categories'))
                        ->multiple()
                        ->options(fn (Get $get) => EntityCategory::where('entity_id', $get('entity_id'))->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get) => $get('entity_id') && EntityCategory::where('entity_id', $get('entity_id'))->exists())
                        ->helperText(Label::trans('flex-fields::flex-fields.custom_field.helpers.categories', default: 'If selected, this field will only be available when the entity record belongs to one of these categories.')),

                    Forms\Components\Select::make('type')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.type'))
                        ->options($fieldTypes)
                        ->required()
                        ->default('text')
                        ->live(),

                    Forms\Components\TextInput::make('label')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.label'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(
                            fn ($state, Set $set) => $set('key', Keys::make((string) $state))
                        ),

                    Forms\Components\TextInput::make('key')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.key'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(Label::trans('flex-fields::flex-fields.custom_field.helpers.key')),

                    Forms\Components\Textarea::make('description')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.description'))
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('placeholder')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.placeholder'))
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => in_array($get('type'), [
                            'text',
                            'textarea',
                            'number',
                            'email',
                            'url',
                        ])),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.options'))
                ->visible(fn (Get $get) => in_array($get('type'), ['select', 'multiselect']))
                ->schema([
                    Forms\Components\Repeater::make('options')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.choices'))
                        ->schema([
                            Grid::make(2)->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.option_value'))
                                    ->required(),
                                Forms\Components\TextInput::make('label')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.option_label'))
                                    ->required(),
                            ]),
                        ])
                        ->addActionLabel(Label::trans('flex-fields::flex-fields.custom_field.actions.add_option'))
                        ->reorderable()
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.repeater_schema'))
                ->visible(fn (Get $get) => $get('type') === 'repeater')
                ->schema([
                    Forms\Components\Repeater::make('settings.schema')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.sub_fields'))
                        ->schema([
                            Grid::make(2)->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.label'))
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn ($state, Set $set, Get $get) => $get('key') ? null : $set('key', Keys::make((string) $state))
                                    ),
                                Forms\Components\TextInput::make('key')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.key'))
                                    ->required(),
                                Forms\Components\Select::make('type')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.type'))
                                    ->options($fieldTypes)
                                    ->default('text')
                                    ->required(),
                                Forms\Components\Select::make('width')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.width'))
                                    ->options([
                                        'full' => Label::trans('flex-fields::flex-fields.custom_field.widths.full'),
                                        'half' => Label::trans('flex-fields::flex-fields.custom_field.widths.half'),
                                        'third' => Label::trans('flex-fields::flex-fields.custom_field.widths.third'),
                                    ])
                                    ->default('full'),
                                Forms\Components\Toggle::make('is_required')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.is_required'))
                                    ->default(false)
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->addActionLabel(Label::trans('flex-fields::flex-fields.custom_field.actions.add_sub_field'))
                        ->reorderable()
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.relationship'))
                ->visible(fn (Get $get) => $get('type') === 'relationship')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('settings.related_entity')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.related_entity'))
                        ->options(fn () => Entity::currentTenant()->orderBy('name')->pluck('name', 'slug'))
                        ->searchable()
                        ->required(fn (Get $get) => $get('type') === 'relationship'),

                    Forms\Components\Toggle::make('settings.multiple')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.relationship_multiple'))
                        ->default(false),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.type_settings'))
                ->visible(fn (Get $get) => in_array($get('type'), ['phone', 'address', ...FieldTypes::TRANSLATABLE], true))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('settings.phone_mask')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.phone_mask'))
                        ->helperText(Label::trans('flex-fields::flex-fields.custom_field.helpers.phone_mask'))
                        ->placeholder('(999) 999-9999')
                        ->visible(fn (Get $get) => $get('type') === 'phone'),

                    Forms\Components\CheckboxList::make('settings.address_parts')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.address_parts'))
                        ->options(collect(FieldTypes::ADDRESS_PARTS)->mapWithKeys(fn (string $part) => [
                            $part => Label::trans("flex-fields::flex-fields.address.{$part}", default: $part),
                        ])->all())
                        ->default(FieldTypes::ADDRESS_PARTS)
                        ->columns(3)
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $get('type') === 'address'),

                    Forms\Components\Toggle::make('settings.translatable')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.translatable'))
                        ->helperText(fn () => Label::trans('flex-fields::flex-fields.custom_field.helpers.translatable', [
                            'locales' => implode(', ', FieldTypes::locales()),
                        ]))
                        ->visible(fn (Get $get) => in_array($get('type'), FieldTypes::TRANSLATABLE, true)),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.image_settings'))
                ->visible(fn (Get $get) => in_array($get('type'), ['image', 'file']))
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('settings.multiple')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.multiple_images'))
                        ->helperText(Label::trans('flex-fields::flex-fields.custom_field.helpers.multiple_images'))
                        ->default(false),

                    Forms\Components\Toggle::make('settings.optimize')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.optimize_images'))
                        ->helperText(Label::trans('flex-fields::flex-fields.custom_field.helpers.optimize_images'))
                        ->default(false)
                        ->live()
                        ->visible(fn (Get $get) => $get('type') === 'image'),

                    Forms\Components\Select::make('settings.image_format')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.image_format'))
                        ->options([
                            'webp' => 'WebP',
                            'jpg' => 'JPG',
                            'png' => 'PNG',
                            'avif' => 'AVIF',
                        ])
                        ->default('webp')
                        ->visible(fn (Get $get) => $get('type') === 'image' && (bool) $get('settings.optimize')),

                    Forms\Components\TextInput::make('settings.image_max_width')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.image_max_width'))
                        ->numeric()
                        ->placeholder('1920')
                        ->visible(fn (Get $get) => $get('type') === 'image'),

                    Forms\Components\TextInput::make('settings.image_max_height')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.image_max_height'))
                        ->numeric()
                        ->placeholder('1080')
                        ->visible(fn (Get $get) => $get('type') === 'image'),

                    Forms\Components\TextInput::make('settings.image_quality')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.image_quality'))
                        ->numeric()
                        ->default(80)
                        ->visible(fn (Get $get) => $get('type') === 'image' && (bool) $get('settings.optimize')),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.validation'))
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('is_required')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.is_required'))
                        ->default(false),

                    Forms\Components\Toggle::make('is_searchable')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.is_searchable'))
                        ->default(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.is_active'))
                        ->default(true),

                    Forms\Components\Select::make('width')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.width'))
                        ->options([
                            'full' => Label::trans('flex-fields::flex-fields.custom_field.widths.full'),
                            'half' => Label::trans('flex-fields::flex-fields.custom_field.widths.half'),
                            'third' => Label::trans('flex-fields::flex-fields.custom_field.widths.third'),
                        ])
                        ->default('full'),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.validation_rules'))
                ->description(Label::trans('flex-fields::flex-fields.custom_field.descriptions.validation_rules'))
                ->collapsible()
                ->schema([
                    Forms\Components\Repeater::make('validation_builder')
                        ->hiddenLabel()
                        ->schema([
                            Grid::make(2)->schema([
                                Forms\Components\Select::make('rule')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.rule'))
                                    ->options(ValidationRules::options())
                                    ->searchable()
                                    ->required()
                                    ->live(),
                                Forms\Components\TextInput::make('value')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.rule_value'))
                                    ->visible(fn (Get $get) => ValidationRules::needsValue($get('rule')))
                                    ->required(fn (Get $get) => ValidationRules::needsValue($get('rule'))),
                            ]),
                        ])
                        ->default([])
                        ->addActionLabel(Label::trans('flex-fields::flex-fields.custom_field.actions.add_rule'))
                        ->reorderable(false)
                        ->columnSpanFull(),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.conditional'))
                ->description(Label::trans('flex-fields::flex-fields.custom_field.descriptions.conditional'))
                ->collapsible()
                ->schema([
                    Forms\Components\Select::make('show_when_match')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.show_when_match'))
                        ->options([
                            'all' => Label::trans('flex-fields::flex-fields.custom_field.show_when.all'),
                            'any' => Label::trans('flex-fields::flex-fields.custom_field.show_when.any'),
                        ])
                        ->default('all'),

                    Forms\Components\Repeater::make('show_when_conditions')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.show_when_conditions'))
                        ->schema([
                            Grid::make(3)->schema([
                                Forms\Components\Select::make('field')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.condition_field'))
                                    ->options(fn (Get $get, ?CustomField $record) => CustomField::query()
                                        ->where('entity_id', $get('../../entity_id'))
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->orderBy('order')
                                        ->pluck('label', 'key'))
                                    ->required(),
                                Forms\Components\Select::make('operator')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.condition_operator'))
                                    ->options(collect(ConditionEvaluator::OPERATORS)->mapWithKeys(fn (string $op) => [
                                        $op => Label::trans("flex-fields::flex-fields.custom_field.operators.{$op}", default: $op),
                                    ])->all())
                                    ->default('equals')
                                    ->required()
                                    ->live(),
                                Forms\Components\TextInput::make('value')
                                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.condition_value'))
                                    ->helperText(fn (Get $get) => in_array($get('operator'), ['in', 'not_in'], true)
                                        ? Label::trans('flex-fields::flex-fields.custom_field.helpers.condition_list')
                                        : null)
                                    ->hidden(fn (Get $get) => in_array($get('operator'), ConditionEvaluator::VALUELESS, true)),
                            ]),
                        ])
                        ->default([])
                        ->addActionLabel(Label::trans('flex-fields::flex-fields.custom_field.actions.add_condition'))
                        ->columnSpanFull(),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.default_value'))
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('default_value')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.default_value'))
                        ->visible(fn (Get $get) => ! in_array($get('type'), [
                            'boolean',
                            'file',
                            'image',
                            'richtext',
                        ])),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.custom_field.sections.timestamps'))
                ->columns(2)
                ->visible(fn ($record) => $record !== null)
                ->schema([
                    TextEntry::make('created_at')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.created_at'))
                        ->state(fn ($record) => $record?->created_at ? $record->created_at->format('Y-m-d H:i:s') . ' (' . $record->created_at->diffForHumans() . ')' : '—'),

                    TextEntry::make('updated_at')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.updated_at'))
                        ->state(fn ($record) => $record?->updated_at ? $record->updated_at->format('Y-m-d H:i:s') . ' (' . $record->updated_at->diffForHumans() . ')' : '—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                Tables\Columns\TextColumn::make('entity.name')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.entity'))
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('label')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.label'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (CustomField $record) => $record->key),

                Tables\Columns\TextColumn::make('type')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.table.type'))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state === 'text' => 'gray',
                        $state === 'textarea' => 'info',
                        in_array($state, ['select', 'multiselect', 'repeater']) => 'warning',
                        in_array($state, ['boolean', 'toggle']) => 'success',
                        in_array($state, ['file', 'image']) => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('fieldGroup.label')
                    ->label(Label::trans('flex-fields::flex-fields.field_group.label'))
                    ->visible(fn () => FlexSchema::hasFieldGroups())
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('width')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.table.width'))
                    ->badge(),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.table.required_short'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.table.created_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.table.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('entity_id')
            ->reorderable('order')
            ->filters([
                Tables\Filters\SelectFilter::make('entity_id')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.entity'))
                    ->options(Entity::pluck('name', 'id'))
                    ->default(request()->query('entity'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('type')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.type'))
                    ->options(FieldTypes::options()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.is_active')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomFields::route('/'),
            'create' => Pages\CreateCustomField::route('/create'),
            'edit' => Pages\EditCustomField::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return Label::configOrTrans('flex-fields.navigation_group', 'flex-fields::flex-fields.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.custom_field.label');
    }

    public static function getPluralModelLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.custom_field.plural_label');
    }
}
