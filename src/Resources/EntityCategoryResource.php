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
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityCategory;
use IvanMercedes\FlexFields\Resources\EntityCategoryResource\Pages;
use IvanMercedes\FlexFields\Support\Label;
use UnitEnum;

class EntityCategoryResource extends Resource
{
    protected static ?string $model = EntityCategory::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-tag';

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'ff-categories';

    public static function form(Schema $schema): Schema
    {
        $entity = static::getCurrentEntity();

        return $schema->components([
            Forms\Components\Hidden::make('entity_id')
                ->default($entity?->id),

            Section::make(Label::trans('flex-fields::flex-fields.category.sections.details'))
                ->description(Label::trans('flex-fields::flex-fields.category.descriptions.details'))
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(Label::trans('flex-fields::flex-fields.category.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set, $get, $record) {
                                if (empty($state)) {
                                    return;
                                }
                                $slug = Str::slug($state);
                                $originalSlug = $slug;
                                $count = 1;
                                $entityId = $get('entity_id');
                                while (EntityCategory::where('slug', $slug)
                                    ->where('entity_id', $entityId)
                                    ->where('id', '!=', $record?->id)
                                    ->exists()) {
                                    $slug = $originalSlug . '-' . $count;
                                    $count++;
                                }
                                $set('slug', $slug);
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label(Label::trans('flex-fields::flex-fields.category.fields.slug'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) use ($entity) {
                                if ($entity) {
                                    return $rule->where('entity_id', $entity->id);
                                }

                                return $rule;
                            })
                            ->helperText(Label::trans('flex-fields::flex-fields.category.helpers.slug')),
                    ]),
                ]),

            Section::make(Label::trans('flex-fields::flex-fields.category.sections.hierarchy'))
                ->description(Label::trans('flex-fields::flex-fields.category.descriptions.hierarchy'))
                ->columnSpanFull()
                ->schema([
                    Forms\Components\Select::make('parent_id')
                        ->label(Label::trans('flex-fields::flex-fields.category.fields.parent'))
                        ->relationship('parent', 'name', fn (Builder $query) => $query->where('entity_id', $entity?->id ?? 0))
                        ->searchable()
                        ->preload()
                        ->placeholder(Label::trans('flex-fields::flex-fields.category.placeholders.parent'))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label(Label::trans('flex-fields::flex-fields.category.fields.description'))
                        ->maxLength(65535)
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $entity = static::getCurrentEntity();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($entity) {
                $query->with(['parent']);

                if ($entity) {
                    $query->where('entity_id', $entity->id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(Label::trans('flex-fields::flex-fields.category.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label(Label::trans('flex-fields::flex-fields.category.fields.slug'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label(Label::trans('flex-fields::flex-fields.category.fields.parent'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(Label::trans('flex-fields::flex-fields.category.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                //
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

    public static function getCurrentEntity(): ?Entity
    {
        if ($entityId = self::resolveEntityId()) {
            session(['ff_current_entity' => $entityId]);

            return Entity::find($entityId);
        }

        return self::getDefaultEntity();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntityCategories::route('/'),
            'create' => Pages\CreateEntityCategory::route('/create'),
            'edit' => Pages\EditEntityCategory::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.category.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return Label::configOrTrans('flex-fields.navigation_group', 'flex-fields::flex-fields.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.category.label');
    }

    public static function getPluralModelLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.category.plural_label');
    }

    protected static function resolveEntityId(): ?string
    {
        $entityId = request()->get('entity')
            ?? request()->route('entity')
            ?? self::getFromRouteRecord()
            ?? self::getFromReferer()
            ?? session('ff_current_entity');

        return $entityId !== null ? (string) $entityId : null;
    }

    protected static function getFromRouteRecord(): ?int
    {
        $record = request()->route('record');

        if (! $record) {
            return null;
        }

        if (! $record instanceof EntityCategory) {
            $record = EntityCategory::find($record);
        }

        return $record?->entity_id;
    }

    protected static function getFromReferer(): ?int
    {
        $referer = request()->header('Referer');

        if (! $referer) {
            return null;
        }

        // Query string (?entity=1)
        $query = parse_url($referer, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $params);

            if (! empty($params['entity'])) {
                return (int) $params['entity'];
            }
        }

        // URL /ff-categories/{id}/edit
        $path = parse_url($referer, PHP_URL_PATH);

        if ($path && preg_match('#/ff-categories/([^/?]+)/edit#', $path, $matches)) {
            return EntityCategory::find($matches[1])?->entity_id;
        }

        return null;
    }

    protected static function getDefaultEntity(): ?Entity
    {
        $entity = Entity::where('is_active', true)->first();

        if ($entity) {
            session(['ff_current_entity' => $entity->id]);
        }

        return $entity;
    }
}
