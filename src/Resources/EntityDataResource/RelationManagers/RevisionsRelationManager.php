<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityDataResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Models\RecordRevision;
use IvanMercedes\FlexFields\Resources\EntityDataResource;
use IvanMercedes\FlexFields\Services\RecordHistoryService;
use IvanMercedes\FlexFields\Support\Label;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return app(RecordHistoryService::class)->enabled();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return Label::trans('flex-fields::flex-fields.history.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event')
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->width('60px'),

                Tables\Columns\TextColumn::make('event')
                    ->label(Label::trans('flex-fields::flex-fields.history.fields.event'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Label::trans("flex-fields::flex-fields.history.events.{$state}", default: $state))
                    ->color(fn (string $state): string => match ($state) {
                        RecordHistoryService::EVENT_CREATED => 'success',
                        RecordHistoryService::EVENT_UPDATED => 'info',
                        RecordHistoryService::EVENT_DELETED => 'danger',
                        RecordHistoryService::EVENT_RESTORED, RecordHistoryService::EVENT_REVERTED => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('user_name')
                    ->label(Label::trans('flex-fields::flex-fields.history.fields.user'))
                    ->default('—'),

                Tables\Columns\TextColumn::make('diff')
                    ->label(Label::trans('flex-fields::flex-fields.history.fields.changed'))
                    ->state(fn (RecordRevision $record) => implode(', ', $record->changedKeys()) ?: '—')
                    ->wrap()
                    ->limit(80),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(Label::trans('flex-fields::flex-fields.history.fields.date'))
                    ->dateTime()
                    ->description(fn (RecordRevision $record) => $record->created_at?->diffForHumans()),
            ])
            ->recordActions([
                Action::make('view_changes')
                    ->label(Label::trans('flex-fields::flex-fields.history.actions.view'))
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (RecordRevision $record) => Label::trans('flex-fields::flex-fields.history.actions.view') . ' #' . $record->id)
                    ->modalContent(fn (RecordRevision $record) => view('flex-fields::filament.revisions.changes', ['revision' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(Label::trans('flex-fields::flex-fields.history.actions.close')),

                Action::make('revert')
                    ->label(Label::trans('flex-fields::flex-fields.history.actions.revert'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(Label::trans('flex-fields::flex-fields.history.actions.revert_confirm'))
                    ->visible(fn () => $this->getOwnerRecord() instanceof EntityRecord
                        && FlexPermissions::allows(null, FlexPermissions::UPDATE, $this->getOwnerRecord()->entity))
                    ->action(function (RecordRevision $record) {
                        app(RecordHistoryService::class)->revert($record);

                        Notification::make()
                            ->success()
                            ->title(Label::trans('flex-fields::flex-fields.history.notifications.reverted'))
                            ->send();

                        $this->redirect(EntityDataResource::getUrl('edit', ['record' => $this->getOwnerRecord()]));
                    }),
            ]);
    }
}
