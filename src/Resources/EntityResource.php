<?php

namespace IvanMercedes\FlexFields\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Resources\EntityResource\Pages;
use IvanMercedes\FlexFields\Support\Label;
use UnitEnum;

class EntityResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ff-entities';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(Label::trans('flex-fields::flex-fields.entity.sections.details'))
                ->description(Label::trans('flex-fields::flex-fields.entity.descriptions.details'))
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(Label::trans('flex-fields::flex-fields.entity.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn ($state, Set $set) => $set('slug', \Illuminate\Support\Str::slug($state))
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->label(Label::trans('flex-fields::flex-fields.entity.fields.slug'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText(Label::trans('flex-fields::flex-fields.entity.helpers.slug')),
                    ]),

                    Forms\Components\Textarea::make('description')
                        ->label(Label::trans('flex-fields::flex-fields.entity.fields.description'))
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.entity.sections.appearance'))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('icon')
                        ->label(Label::trans('flex-fields::flex-fields.entity.fields.icon'))
                        ->placeholder(Label::trans('flex-fields::flex-fields.entity.placeholders.icon'))
                        ->helperText(Label::trans('flex-fields::flex-fields.entity.helpers.icon'))
                        ->default('heroicon-o-cube'),

                    Forms\Components\ColorPicker::make('color')
                        ->label(Label::trans('flex-fields::flex-fields.entity.fields.color'))
                        ->default('#6366f1'),

                    Forms\Components\TextInput::make('menu_order')
                        ->label(Label::trans('flex-fields::flex-fields.entity.fields.menu_order'))
                        ->numeric()
                        ->default(0),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.entity.sections.visibility'))
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label(Label::trans('flex-fields::flex-fields.entity.fields.is_active'))
                        ->default(true)
                        ->helperText(Label::trans('flex-fields::flex-fields.entity.helpers.is_active')),

                    Forms\Components\Toggle::make('show_in_menu')
                        ->label(Label::trans('flex-fields::flex-fields.entity.fields.show_in_menu'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->width('40px'),

                Tables\Columns\TextColumn::make('name')
                    ->label(Label::trans('flex-fields::flex-fields.entity.table.entity'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Entity $record) => $record->slug),

                Tables\Columns\TextColumn::make('custom_fields_count')
                    ->counts('customFields')
                    ->label(Label::trans('flex-fields::flex-fields.entity.table.fields'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('records_count')
                    ->counts('records')
                    ->label(Label::trans('flex-fields::flex-fields.entity.table.records'))
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(Label::trans('flex-fields::flex-fields.entity.fields.is_active'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('show_in_menu')
                    ->label(Label::trans('flex-fields::flex-fields.entity.table.in_menu'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('menu_order')
                    ->label(Label::trans('flex-fields::flex-fields.entity.table.order'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(Label::trans('flex-fields::flex-fields.entity.table.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('menu_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(Label::trans('flex-fields::flex-fields.entity.fields.is_active')),
            ])
            ->actions([
                Action::make('manage_fields')
                    ->label(Label::trans('flex-fields::flex-fields.entity.actions.manage_fields'))
                    ->icon('heroicon-o-variable')
                    ->color('info')
                    ->url(
                        fn (Entity $record) => CustomFieldResource::getUrl('index', ['tableFilters[entity_id][value]' => $record->id])
                    ),

                Action::make('view_records')
                    ->label(Label::trans('flex-fields::flex-fields.entity.actions.view_records'))
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->url(
                        fn (Entity $record) => EntityDataResource::getUrl('index', ['entity' => $record->id])
                    ),

                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Entity $record, DeleteAction $action) {
                        $recordsCount = $record->records()->count();

                        if ($recordsCount > 0) {
                            Notification::make()
                                ->danger()
                                ->title(Label::trans('flex-fields::flex-fields.entity.notifications.cannot_delete_title'))
                                ->body(Label::trans('flex-fields::flex-fields.entity.notifications.cannot_delete_body', [
                                    'count' => $recordsCount,
                                ]))
                                ->send();

                            $action->cancel();
                        }
                    }),
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
            'index' => Pages\ListEntities::route('/'),
            'create' => Pages\CreateEntity::route('/create'),
            'edit' => Pages\EditEntity::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count() ?: null;
    }

    public static function getNavigationGroup(): ?string
    {
        return Label::configOrTrans('flex-fields.navigation_group', 'flex-fields::flex-fields.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.entity.label');
    }

    public static function getPluralModelLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.entity.plural_label');
    }
}
