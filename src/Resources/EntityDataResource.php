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
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;
use IvanMercedes\FlexFields\Support\DynamicTableBuilder;
use IvanMercedes\FlexFields\Support\Label;
use UnitEnum;

class EntityDataResource extends Resource
{
    protected static ?string $model = EntityRecord::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-table-cells';

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'ff-data';

    public static function form(Schema $schema): Schema
    {
        $entity = static::getCurrentEntity();

        $components = [
            Forms\Components\Hidden::make('entity_id')
                ->default($entity?->id),

            Forms\Components\TextInput::make('title')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.title'))
                ->placeholder(Label::trans('flex-fields::flex-fields.record.placeholders.title'))
                ->maxLength(255),

            Forms\Components\Select::make('status')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.status'))
                ->options(static::getStatusOptions())
                ->default('published')
                ->required(),

            Forms\Components\Select::make('categories')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.categories'))
                ->multiple()
                ->relationship('categories', 'name', fn (Builder $query) => $query->where('entity_id', $entity?->id ?? 0))
                ->preload(),
        ];

        if ($entity) {
            $components = array_merge($components, DynamicFormBuilder::build($entity));
        }

        return $schema->components($components);
    }

    public static function table(Table $table): Table
    {
        $entity = static::getCurrentEntity();
        $columns = DynamicTableBuilder::build($entity);

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($entity) {
                $query->with(['entity', 'fieldValues.customField']);

                if ($entity) {
                    $query->where('entity_id', $entity->id);
                }
            })
            ->columns(array_merge([
                Tables\Columns\TextColumn::make('id')
                    ->label(Label::trans('flex-fields::flex-fields.record.fields.id'))
                    ->sortable()
                    ->width('60px'),

                Tables\Columns\TextColumn::make('title')
                    ->label(Label::trans('flex-fields::flex-fields.record.fields.title_column'))
                    ->searchable()
                    ->sortable()
                    ->default('—'),

                Tables\Columns\TextColumn::make('status')
                    ->label(Label::trans('flex-fields::flex-fields.record.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => $state ? (static::getStatusOptions()[$state] ?? $state) : null)
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
            ], $columns, [
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(Label::trans('flex-fields::flex-fields.record.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ]))
            ->filters([
                Tables\Filters\SelectFilter::make('entity_id')
                    ->label(Label::trans('flex-fields::flex-fields.custom_field.fields.entity'))
                    ->options(Entity::where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('status')
                    ->label(Label::trans('flex-fields::flex-fields.record.fields.status'))
                    ->options(static::getStatusOptions()),
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
            'index' => Pages\ListEntityData::route('/'),
            'create' => Pages\CreateEntityData::route('/create'),
            'edit' => Pages\EditEntityData::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.record.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return Label::configOrTrans('flex-fields.navigation_group', 'flex-fields::flex-fields.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.record.label');
    }

    public static function getPluralModelLabel(): string
    {
        return Label::trans('flex-fields::flex-fields.record.plural_label');
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

        if (! $record instanceof EntityRecord) {
            $record = EntityRecord::find($record);
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

        // URL tipo /ff-data/{id}/edit
        $path = parse_url($referer, PHP_URL_PATH);

        if ($path && preg_match('#/ff-data/([^/?]+)/edit#', $path, $matches)) {
            return EntityRecord::find($matches[1])?->entity_id;
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

    protected static function getStatusOptions(): array
    {
        return [
            'draft' => Label::trans('flex-fields::flex-fields.record.statuses.draft'),
            'published' => Label::trans('flex-fields::flex-fields.record.statuses.published'),
            'archived' => Label::trans('flex-fields::flex-fields.record.statuses.archived'),
        ];
    }
}
