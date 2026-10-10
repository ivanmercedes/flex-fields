<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\FlexFieldsPlugin;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityCategory;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Resources\EntityDataResource\Pages;
use IvanMercedes\FlexFields\Resources\EntityDataResource\RelationManagers\RevisionsRelationManager;
use IvanMercedes\FlexFields\Services\RecordExporter;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;
use IvanMercedes\FlexFields\Support\FlexModels;
use IvanMercedes\FlexFields\Support\Label;
use Throwable;
use UnitEnum;

class EntityDataResource extends Resource
{
    protected static ?string $model = EntityRecord::class; // see getModel()

    protected static bool $shouldRegisterNavigation = true;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-table-cells';

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'ff-data';

    public static function getModel(): string
    {
        return FlexModels::record();
    }

    /**
     * Per-entity permissions (when enabled) on top of the regular policies.
     */
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
    {
        if (FlexPermissions::enabled()) {
            $method = $action instanceof BackedEnum ? (string) $action->value : ($action instanceof UnitEnum ? $action->name : $action);
            $ability = FlexPermissions::abilityForPolicyMethod($method);
            $entity = $record instanceof EntityRecord ? $record->entity : static::resolveEntitySafely();

            if ($ability !== null && $entity !== null && ! FlexPermissions::allows(null, $ability, $entity)) {
                return Response::deny();
            }
        }

        return parent::getAuthorizationResponse($action, $record);
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

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
                ->live()
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

            Forms\Components\DateTimePicker::make('created_at')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.created_at'))
                ->default(now())
                ->seconds(false)
                ->native(false),

            Forms\Components\DateTimePicker::make('updated_at')
                ->label(Label::trans('flex-fields::flex-fields.record.fields.updated_at'))
                ->default(now())
                ->seconds(false)
                ->native(false),
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label(Label::trans('flex-fields::flex-fields.record.fields.created_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('export_selected')
                        ->label(Label::trans('flex-fields::flex-fields.export.actions.export_selected'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn () => $entity !== null && FlexPermissions::allows(null, FlexPermissions::EXPORT, $entity))
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => app(RecordExporter::class)->download(
                            $entity,
                            $records->load(['categories', 'fieldValues.customField']),
                        )),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('export_csv')
                    ->label(Label::trans('flex-fields::flex-fields.export.actions.export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn () => $entity !== null && FlexPermissions::allows(null, FlexPermissions::EXPORT, $entity))
                    ->action(fn (HasTable $livewire) => app(RecordExporter::class)->download(
                        $entity,
                        $livewire->getFilteredTableQuery()->where('entity_id', $entity->id),
                    )),

                Action::make('import_records')
                    ->label(Label::trans('flex-fields::flex-fields.import.actions.open'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->visible(fn () => $entity !== null && static::canCreate() && FlexPermissions::allows(null, FlexPermissions::IMPORT, $entity))
                    ->url(fn () => $entity ? static::getUrl('import', ['entity' => $entity->id]) : null),

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
            $entity = Entity::currentTenant()->find($entityId);

            if (! $entity) {
                // If an entity ID was requested but it doesn't belong to the current tenant, abort.
                abort(404, 'Entity not found or access denied for this tenant.');
            }

            session(['ff_current_entity' => $entity->id]);

            return $entity;
        }

        return self::getDefaultEntity();
    }

    public static function getNavigationItems(): array
    {
        /** @var FlexFieldsPlugin|null $plugin */
        $plugin = Filament::getCurrentPanel()?->getPlugin('flex-fields');

        if ($plugin && ! $plugin->shouldShowEntitiesInMenu()) {
            return [];
        }

        $entities = Entity::currentTenant()
            ->where('is_active', true)
            ->where('show_in_menu', true)
            ->orderBy('menu_order')
            ->get();

        $navigationItems = [];

        foreach ($entities as $entity) {
            if (! FlexPermissions::allows(null, FlexPermissions::VIEW_ANY, $entity)) {
                continue;
            }

            $navigationItems[] = NavigationItem::make("entity-{$entity->slug}")
                ->label($entity->name)
                ->icon($entity->icon ?? 'heroicon-o-cube')
                ->url(fn (): string => static::getUrl('index', ['entity' => $entity->id]))
                ->sort($entity->menu_order + 100)
                ->isActiveWhen(fn (): bool => request()->get('entity') == $entity->id
                    && request()->routeIs('filament.*.resources.ff-data.*'));
        }

        return $navigationItems;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntityData::route('/'),
            'create' => Pages\CreateEntityData::route('/create'),
            'import' => Pages\ImportEntityData::route('/import'),
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

    /**
     * Resolve the current entity without aborting (used by authorization checks).
     */
    protected static function resolveEntitySafely(): ?Entity
    {
        try {
            $entityId = self::resolveEntityId();

            if ($entityId !== null) {
                return Entity::currentTenant()->find($entityId);
            }

            return Entity::currentTenant()->where('is_active', true)->first();
        } catch (Throwable) {
            return null;
        }
    }

    protected static function getDefaultEntity(): ?Entity
    {
        $entity = Entity::currentTenant()->where('is_active', true)->first();

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
