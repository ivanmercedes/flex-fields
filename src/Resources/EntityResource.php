<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Resources\EntityResource\Pages;
use IvanMercedes\FlexFields\Support\Label;
use UnitEnum;

class EntityResource extends Resource
{
    protected static ?string $model = Entity::class;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-cube';

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ff-entities';

    public static function isScopedToTenant(): bool
    {
        return config('flex-fields.tenancy.enabled', false) && config('flex-fields.tenancy.is_tenant_aware.entities', true);
    }

    public static function getTenantOwnershipRelationshipName(): string
    {
        return 'tenant';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(Label::trans('flex-fields::flex-fields.entity.sections.details'))
                ->description(Label::trans('flex-fields::flex-fields.entity.descriptions.details'))
                ->schema([
                    Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(Label::trans('flex-fields::flex-fields.entity.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set, $record) {
                                if (empty($state)) {
                                    return;
                                }
                                $slug = Str::slug($state);
                                $originalSlug = $slug;
                                $count = 1;
                                while (Entity::where('slug', $slug)->where('id', '!=', $record?->id)->exists()) {
                                    $slug = $originalSlug . '-' . $count;
                                    $count++;
                                }
                                $set('slug', $slug);
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label(Label::trans('flex-fields::flex-fields.entity.fields.slug'))
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
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('icon')
                        ->label(Label::trans('flex-fields::flex-fields.entity.fields.icon'))
                        ->options(self::getIconOptions())
                        ->searchable()
                        ->allowHtml()
                        ->default('heroicon-o-cube'),

                    Forms\Components\ColorPicker::make('color')
                        ->label(Label::trans('flex-fields::flex-fields.entity.fields.color'))
                        ->default('#6366f1'),
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
                        ->default(true)
                        ->visible(fn () => Filament::getCurrentPanel()?->getPlugin('flex-fields')?->shouldShowEntitiesInMenu() ?? true),
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
                    ->boolean()
                    ->visible(fn () => Filament::getCurrentPanel()?->getPlugin('flex-fields')?->shouldShowEntitiesInMenu() ?? true),

                Tables\Columns\TextColumn::make('menu_order')
                    ->label(Label::trans('flex-fields::flex-fields.entity.table.order'))
                    ->sortable()
                    ->visible(fn () => Filament::getCurrentPanel()?->getPlugin('flex-fields')?->shouldShowEntitiesInMenu() ?? true),

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
                        fn (Entity $record) => CustomFieldResource::getUrl('index', ['entity' => $record->id])
                    ),

                Action::make('view_records')
                    ->label(Label::trans('flex-fields::flex-fields.entity.actions.view_records'))
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->url(
                        fn (Entity $record) => EntityDataResource::getUrl('index', ['entity' => $record->id])
                    ),

                Action::make('manage_categories')
                    ->label(Label::trans('flex-fields::flex-fields.entity.actions.manage_categories'))
                    ->icon('heroicon-o-tag')
                    ->color('warning')
                    ->url(
                        fn (Entity $record) => EntityCategoryResource::getUrl('index', ['entity' => $record->id])
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

    protected static function getIconOptions(): array
    {
        $icons = [
            'heroicon-o-academic-cap',
            'heroicon-o-adjustments-horizontal',
            'heroicon-o-adjustments-vertical',
            'heroicon-o-archive-box-arrow-down',
            'heroicon-o-archive-box-x-mark',
            'heroicon-o-archive-box',
            'heroicon-o-arrow-down-circle',
            'heroicon-o-arrow-down-left',
            'heroicon-o-arrow-down-on-square-stack',
            'heroicon-o-arrow-down-on-square',
            'heroicon-o-arrow-down-right',
            'heroicon-o-arrow-down-tray',
            'heroicon-o-arrow-down',
            'heroicon-o-arrow-left-circle',
            'heroicon-o-arrow-left-end-on-rectangle',
            'heroicon-o-arrow-left-start-on-rectangle',
            'heroicon-o-arrow-left',
            'heroicon-o-arrow-long-down',
            'heroicon-o-arrow-long-left',
            'heroicon-o-arrow-long-right',
            'heroicon-o-arrow-long-up',
            'heroicon-o-arrow-path-rounded-square',
            'heroicon-o-arrow-path',
            'heroicon-o-arrow-right-circle',
            'heroicon-o-arrow-right-end-on-rectangle',
            'heroicon-o-arrow-right-start-on-rectangle',
            'heroicon-o-arrow-right',
            'heroicon-o-arrow-top-right-on-square',
            'heroicon-o-arrow-trending-down',
            'heroicon-o-arrow-trending-up',
            'heroicon-o-arrow-turn-down-left',
            'heroicon-o-arrow-turn-down-right',
            'heroicon-o-arrow-turn-left-down',
            'heroicon-o-arrow-turn-left-up',
            'heroicon-o-arrow-turn-right-down',
            'heroicon-o-arrow-turn-right-up',
            'heroicon-o-arrow-turn-up-left',
            'heroicon-o-arrow-turn-up-right',
            'heroicon-o-arrow-up-circle',
            'heroicon-o-arrow-up-left',
            'heroicon-o-arrow-up-on-square-stack',
            'heroicon-o-arrow-up-on-square',
            'heroicon-o-arrow-up-right',
            'heroicon-o-arrow-up-tray',
            'heroicon-o-arrow-up',
            'heroicon-o-arrow-uturn-down',
            'heroicon-o-arrow-uturn-left',
            'heroicon-o-arrow-uturn-right',
            'heroicon-o-arrow-uturn-up',
            'heroicon-o-arrows-pointing-in',
            'heroicon-o-arrows-pointing-out',
            'heroicon-o-arrows-right-left',
            'heroicon-o-arrows-up-down',
            'heroicon-o-at-symbol',
            'heroicon-o-backspace',
            'heroicon-o-backward',
            'heroicon-o-banknotes',
            'heroicon-o-bars-2',
            'heroicon-o-bars-3-bottom-left',
            'heroicon-o-bars-3-bottom-right',
            'heroicon-o-bars-3-center-left',
            'heroicon-o-bars-3',
            'heroicon-o-bars-4',
            'heroicon-o-bars-arrow-down',
            'heroicon-o-bars-arrow-up',
            'heroicon-o-battery-0',
            'heroicon-o-battery-100',
            'heroicon-o-battery-50',
            'heroicon-o-beaker',
            'heroicon-o-bell-alert',
            'heroicon-o-bell-slash',
            'heroicon-o-bell-snooze',
            'heroicon-o-bell',
            'heroicon-o-bold',
            'heroicon-o-bolt-slash',
            'heroicon-o-bolt',
            'heroicon-o-book-open',
            'heroicon-o-bookmark-slash',
            'heroicon-o-bookmark-square',
            'heroicon-o-bookmark',
            'heroicon-o-briefcase',
            'heroicon-o-bug-ant',
            'heroicon-o-building-library',
            'heroicon-o-building-office-2',
            'heroicon-o-building-office',
            'heroicon-o-building-storefront',
            'heroicon-o-cake',
            'heroicon-o-calculator',
            'heroicon-o-calendar-date-range',
            'heroicon-o-calendar-days',
            'heroicon-o-calendar',
            'heroicon-o-camera',
            'heroicon-o-chart-bar-square',
            'heroicon-o-chart-bar',
            'heroicon-o-chart-pie',
            'heroicon-o-chat-bubble-bottom-center-text',
            'heroicon-o-chat-bubble-bottom-center',
            'heroicon-o-chat-bubble-left-ellipsis',
            'heroicon-o-chat-bubble-left-right',
            'heroicon-o-chat-bubble-left',
            'heroicon-o-chat-bubble-oval-left-ellipsis',
            'heroicon-o-chat-bubble-oval-left',
            'heroicon-o-check-badge',
            'heroicon-o-check-circle',
            'heroicon-o-check',
            'heroicon-o-chevron-double-down',
            'heroicon-o-chevron-double-left',
            'heroicon-o-chevron-double-right',
            'heroicon-o-chevron-double-up',
            'heroicon-o-chevron-down',
            'heroicon-o-chevron-left',
            'heroicon-o-chevron-right',
            'heroicon-o-chevron-up-down',
            'heroicon-o-chevron-up',
            'heroicon-o-circle-stack',
            'heroicon-o-clipboard-document-check',
            'heroicon-o-clipboard-document-list',
            'heroicon-o-clipboard-document',
            'heroicon-o-clipboard',
            'heroicon-o-clock',
            'heroicon-o-cloud-arrow-down',
            'heroicon-o-cloud-arrow-up',
            'heroicon-o-cloud',
            'heroicon-o-code-bracket-square',
            'heroicon-o-code-bracket',
            'heroicon-o-cog-6-tooth',
            'heroicon-o-cog-8-tooth',
            'heroicon-o-cog',
            'heroicon-o-command-line',
            'heroicon-o-computer-desktop',
            'heroicon-o-cpu-chip',
            'heroicon-o-credit-card',
            'heroicon-o-cube-transparent',
            'heroicon-o-cube',
            'heroicon-o-currency-bangladeshi',
            'heroicon-o-currency-dollar',
            'heroicon-o-currency-euro',
            'heroicon-o-currency-pound',
            'heroicon-o-currency-rupee',
            'heroicon-o-currency-yen',
            'heroicon-o-cursor-arrow-rays',
            'heroicon-o-cursor-arrow-ripple',
            'heroicon-o-device-phone-mobile',
            'heroicon-o-device-tablet',
            'heroicon-o-divide',
            'heroicon-o-document-arrow-down',
            'heroicon-o-document-arrow-up',
            'heroicon-o-document-chart-bar',
            'heroicon-o-document-check',
            'heroicon-o-document-currency-bangladeshi',
            'heroicon-o-document-currency-dollar',
            'heroicon-o-document-currency-euro',
            'heroicon-o-document-currency-pound',
            'heroicon-o-document-currency-rupee',
            'heroicon-o-document-currency-yen',
            'heroicon-o-document-duplicate',
            'heroicon-o-document-magnifying-glass',
            'heroicon-o-document-minus',
            'heroicon-o-document-plus',
            'heroicon-o-document-text',
            'heroicon-o-document',
            'heroicon-o-ellipsis-horizontal-circle',
            'heroicon-o-ellipsis-horizontal',
            'heroicon-o-ellipsis-vertical',
            'heroicon-o-envelope-open',
            'heroicon-o-envelope',
            'heroicon-o-equals',
            'heroicon-o-exclamation-circle',
            'heroicon-o-exclamation-triangle',
            'heroicon-o-eye-dropper',
            'heroicon-o-eye-slash',
            'heroicon-o-eye',
            'heroicon-o-face-frown',
            'heroicon-o-face-smile',
            'heroicon-o-film',
            'heroicon-o-finger-print',
            'heroicon-o-fire',
            'heroicon-o-flag',
            'heroicon-o-folder-arrow-down',
            'heroicon-o-folder-minus',
            'heroicon-o-folder-open',
            'heroicon-o-folder-plus',
            'heroicon-o-folder',
            'heroicon-o-forward',
            'heroicon-o-funnel',
            'heroicon-o-gif',
            'heroicon-o-gift-top',
            'heroicon-o-gift',
            'heroicon-o-globe-alt',
            'heroicon-o-globe-americas',
            'heroicon-o-globe-asia-australia',
            'heroicon-o-globe-europe-africa',
            'heroicon-o-h1',
            'heroicon-o-h2',
            'heroicon-o-h3',
            'heroicon-o-hand-raised',
            'heroicon-o-hand-thumb-down',
            'heroicon-o-hand-thumb-up',
            'heroicon-o-hashtag',
            'heroicon-o-heart',
            'heroicon-o-home-modern',
            'heroicon-o-home',
            'heroicon-o-identification',
            'heroicon-o-inbox-arrow-down',
            'heroicon-o-inbox-stack',
            'heroicon-o-inbox',
            'heroicon-o-information-circle',
            'heroicon-o-italic',
            'heroicon-o-key',
            'heroicon-o-language',
            'heroicon-o-lifebuoy',
            'heroicon-o-light-bulb',
            'heroicon-o-link-slash',
            'heroicon-o-link',
            'heroicon-o-list-bullet',
            'heroicon-o-lock-closed',
            'heroicon-o-lock-open',
            'heroicon-o-magnifying-glass-circle',
            'heroicon-o-magnifying-glass-minus',
            'heroicon-o-magnifying-glass-plus',
            'heroicon-o-magnifying-glass',
            'heroicon-o-map-pin',
            'heroicon-o-map',
            'heroicon-o-megaphone',
            'heroicon-o-microphone',
            'heroicon-o-minus-circle',
            'heroicon-o-minus',
            'heroicon-o-moon',
            'heroicon-o-musical-note',
            'heroicon-o-newspaper',
            'heroicon-o-no-symbol',
            'heroicon-o-numbered-list',
            'heroicon-o-paint-brush',
            'heroicon-o-paper-airplane',
            'heroicon-o-paper-clip',
            'heroicon-o-pause-circle',
            'heroicon-o-pause',
            'heroicon-o-pencil-square',
            'heroicon-o-pencil',
            'heroicon-o-percent-badge',
            'heroicon-o-phone-arrow-down-left',
            'heroicon-o-phone-arrow-up-right',
            'heroicon-o-phone-x-mark',
            'heroicon-o-phone',
            'heroicon-o-photo',
            'heroicon-o-play-circle',
            'heroicon-o-play-pause',
            'heroicon-o-play',
            'heroicon-o-plus-circle',
            'heroicon-o-plus',
            'heroicon-o-power',
            'heroicon-o-presentation-chart-bar',
            'heroicon-o-presentation-chart-line',
            'heroicon-o-printer',
            'heroicon-o-puzzle-piece',
            'heroicon-o-qr-code',
            'heroicon-o-question-mark-circle',
            'heroicon-o-queue-list',
            'heroicon-o-radio',
            'heroicon-o-receipt-percent',
            'heroicon-o-receipt-refund',
            'heroicon-o-rectangle-group',
            'heroicon-o-rectangle-stack',
            'heroicon-o-rocket-launch',
            'heroicon-o-rss',
            'heroicon-o-scale',
            'heroicon-o-scissors',
            'heroicon-o-server-stack',
            'heroicon-o-server',
            'heroicon-o-share',
            'heroicon-o-shield-check',
            'heroicon-o-shield-exclamation',
            'heroicon-o-shopping-bag',
            'heroicon-o-shopping-cart',
            'heroicon-o-signal-slash',
            'heroicon-o-signal',
            'heroicon-o-slash',
            'heroicon-o-sparkles',
            'heroicon-o-speaker-wave',
            'heroicon-o-speaker-x-mark',
            'heroicon-o-square-2-stack',
            'heroicon-o-square-3-stack-3d',
            'heroicon-o-squares-2x2',
            'heroicon-o-squares-plus',
            'heroicon-o-star',
            'heroicon-o-stop-circle',
            'heroicon-o-stop',
            'heroicon-o-strikethrough',
            'heroicon-o-sun',
            'heroicon-o-swatch',
            'heroicon-o-table-cells',
            'heroicon-o-tag',
            'heroicon-o-ticket',
            'heroicon-o-trash',
            'heroicon-o-trophy',
            'heroicon-o-truck',
            'heroicon-o-tv',
            'heroicon-o-underline',
            'heroicon-o-user-circle',
            'heroicon-o-user-group',
            'heroicon-o-user-minus',
            'heroicon-o-user-plus',
            'heroicon-o-user',
            'heroicon-o-users',
            'heroicon-o-variable',
            'heroicon-o-video-camera-slash',
            'heroicon-o-video-camera',
            'heroicon-o-view-columns',
            'heroicon-o-viewfinder-circle',
            'heroicon-o-wallet',
            'heroicon-o-wifi',
            'heroicon-o-window',
            'heroicon-o-wrench-screwdriver',
            'heroicon-o-wrench',
            'heroicon-o-x-circle',
            'heroicon-o-x-mark',
        ];

        $options = [];
        foreach ($icons as $icon) {
            $name = (string) str($icon)->after('heroicon-o-')->replace('-', ' ')->title();
            $svg = Blade::render('<x-filament::icon icon="' . $icon . '" class="flex-shrink-0 w-5 h-5 text-gray-500 dark:text-gray-400" />');
            $options[$icon] = "<div class='flex items-center gap-2'>"
                . $svg
                . "<span class='font-medium text-sm'>{$name}</span>"
                . '</div>';
        }

        return $options;
    }
}
