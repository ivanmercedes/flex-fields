<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\EntityResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use IvanMercedes\FlexFields\Models\FieldGroup;
use IvanMercedes\FlexFields\Resources\FieldGroupForm;
use IvanMercedes\FlexFields\Services\FieldGroupDomainService;
use IvanMercedes\FlexFields\Support\FlexSchema;
use IvanMercedes\FlexFields\Support\Label;

class FieldGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'fieldGroups';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return FlexSchema::hasFieldGroups() && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return Label::trans('flex-fields::flex-fields.field_group.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(FieldGroupForm::components());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label(Label::trans('flex-fields::flex-fields.field_group.fields.label'))
                    ->weight('bold')
                    ->description(fn (FieldGroup $record) => $record->key),

                Tables\Columns\TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label(Label::trans('flex-fields::flex-fields.entity.table.fields'))
                    ->badge(),

                Tables\Columns\IconColumn::make('is_collapsible')
                    ->label(Label::trans('flex-fields::flex-fields.field_group.fields.is_collapsible'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_collapsed')
                    ->label(Label::trans('flex-fields::flex-fields.field_group.fields.is_collapsed'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data) => app(FieldGroupDomainService::class)
                        ->create($this->getOwnerRecord()->getKey(), $data, $this->getOwnerRecord()->tenant_id)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (FieldGroup $record, array $data) => app(FieldGroupDomainService::class)
                        ->update($record->entity_id, $record->id, $data, $record->tenant_id)),
                DeleteAction::make()
                    ->using(fn (FieldGroup $record) => app(FieldGroupDomainService::class)
                        ->delete($record->entity_id, $record->id, $record->tenant_id)),
            ]);
    }
}
