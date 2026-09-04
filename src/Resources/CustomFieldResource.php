<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Resources\CustomFieldResource\Pages;
use IvanMercedes\FlexFields\Support\Label;
use UnitEnum;

class CustomFieldResource extends Resource
{
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
        $fieldTypes = Label::options(config('flex-fields.field_types', []));

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

                    Forms\Components\Select::make('category_ids')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.categories', default: 'Categories'))
                        ->multiple()
                        ->options(fn (Get $get) => \IvanMercedes\FlexFields\Models\EntityCategory::where('entity_id', $get('entity_id'))->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get) => $get('entity_id') && \IvanMercedes\FlexFields\Models\EntityCategory::where('entity_id', $get('entity_id'))->exists())
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
                            fn ($state, Set $set) => $set('key', Str::snake($state))
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
                                        fn ($state, Set $set, Get $get) => $get('key') ? null : $set('key', Str::snake($state))
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
                    \Filament\Infolists\Components\TextEntry::make('created_at')
                        ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.created_at'))
                        ->state(fn ($record) => $record?->created_at ? $record->created_at->format('Y-m-d H:i:s') . ' (' . $record->created_at->diffForHumans() . ')' : '—'),

                    \Filament\Infolists\Components\TextEntry::make('updated_at')
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
                    ->options(Label::options(config('flex-fields.field_types', []))),

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
