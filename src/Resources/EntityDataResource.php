<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityCategory;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;
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

    public static function isScopedToTenant(): bool
    {
        return config('flex-fields.tenancy.enabled', false) && config('flex-fields.tenancy.is_tenant_aware.records', true);
    }

    public static function getTenantOwnershipRelationshipName(): string
    {
        return 'tenant';
    }

    public static function form(Schema $schema): Schema
    {
        $entity = static::getCurrentEntity();

        $components = [
            Forms\Components\Hidden::make('entity_id')
                ->default($entity?->id),

            Forms\Components\TextInput::make('title')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.title'))
                ->placeholder(Label::trans('flex-fields::flex-fields.record.placeholders.title'))
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
                    while (EntityRecord::where('slug', $slug)
                        ->where('entity_id', $entityId)
                        ->where('id', '!=', $record?->id)
                        ->exists()) {
                        $slug = $originalSlug . '-' . $count;
                        $count++;
                    }
                    $set('slug', $slug);
                }),

            Forms\Components\TextInput::make('slug')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.slug'))
                ->maxLength(255)
                ->unique(
                    EntityRecord::class,
                    'slug',
                    modifyRuleUsing: function ($rule, $get) {
                        $entityId = $get('entity_id');
                        if ($entityId) {
                            return $rule->where('entity_id', $entityId);
                        }

                        return $rule;
                    }
                ),

            Forms\Components\Select::make('status')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.status'))
                ->options(static::getStatusOptions())
                ->default('published')
                ->required(),

            Forms\Components\Select::make('categories')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.categories'))
                ->multiple()
                ->relationship('categories', 'name', fn (Builder $query) => $query->where('entity_id', $entity?->id ?? 0))
                ->preload()
                ->createOptionForm([
                    Forms\Components\Hidden::make('entity_id')
                        ->default($entity?->id),

                    Forms\Components\TextInput::make('name')
                        ->label(Label::trans('flex-fields::flex-fields.category.fields.name'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if (empty($state)) {
                                return;
                            }
                            $slug = Str::slug($state);
                            $originalSlug = $slug;
                            $count = 1;
                            $entityId = $get('entity_id');
                            while (EntityCategory::where('slug', $slug)
                                ->where('entity_id', $entityId)
                                ->exists()) {
                                $slug = $originalSlug . '-' . $count;
                                $count++;
                            }
                            $set('slug', $slug);
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->label(Label::trans('flex-fields::flex-fields.category.fields.slug'))
                        ->maxLength(255)
                        ->unique(
                            EntityCategory::class,
                            'slug',
                            modifyRuleUsing: function ($rule, Get $get) {
                                $entityId = $get('entity_id');
                                if ($entityId) {
                                    return $rule->where('entity_id', $entityId);
                                }

                                return $rule;
                            }
                        )
                        ->helperText(Label::trans('flex-fields::flex-fields.category.helpers.slug')),

                    Forms\Components\Select::make('parent_id')
                        ->label(Label::trans('flex-fields::flex-fields.category.fields.parent'))
                        ->relationship('parent', 'name', fn (Builder $query) => $query->where('entity_id', $entity?->id ?? 0))
                        ->searchable()
                        ->preload(),

                    Forms\Components\Textarea::make('description')
                        ->label(Label::trans('flex-fields::flex-fields.category.fields.description'))
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ]),
        ];

        if ($entity) {
            $components = array_merge($components, DynamicFormBuilder::build($entity));
        }

        return $schema->components($components);
    }

    public static function table(Table $table): Table
    {
        $entity = static::getCurrentEntity();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($entity) {
                $query->with(['entity', 'fieldValues.customField']);

                if ($entity) {
                    $query->where('entity_id', $entity->id);
                }
            })
            ->columns([
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

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(Label::trans('flex-fields::flex-fields.record.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ])
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
            ])
            ->headerActions([
                Action::make('manage_categories')
                    ->label(Label::trans('flex-fields::flex-fields.entity.actions.manage_categories'))
                    ->icon('heroicon-o-tag')
                    ->color('warning')
                    ->url(fn () => $entity ? EntityCategoryResource::getUrl('index', ['entity' => $entity->id]) : null)
                    ->visible(fn () => $entity !== null),
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
