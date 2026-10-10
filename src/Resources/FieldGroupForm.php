<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources;

use Filament\Forms;
use Filament\Schemas\Components\Utilities\Set;
use IvanMercedes\FlexFields\Support\Keys;
use IvanMercedes\FlexFields\Support\Label;

/**
 * Form components shared by every place a field group can be created/edited.
 */
class FieldGroupForm
{
    /**
     * @return array<int, Forms\Components\Field>
     */
    public static function components(): array
    {
        return [
            Forms\Components\TextInput::make('label')
                ->label(Label::trans('flex-fields::flex-fields.field_group.fields.label'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set) => $set('key', Keys::make((string) $state))),

            Forms\Components\TextInput::make('key')
                ->label(Label::trans('flex-fields::flex-fields.field_group.fields.key'))
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->label(Label::trans('flex-fields::flex-fields.field_group.fields.description'))
                ->rows(2)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('order')
                ->label(Label::trans('flex-fields::flex-fields.field_group.fields.order'))
                ->numeric(),

            Forms\Components\Toggle::make('is_collapsible')
                ->label(Label::trans('flex-fields::flex-fields.field_group.fields.is_collapsible'))
                ->default(true),

            Forms\Components\Toggle::make('is_collapsed')
                ->label(Label::trans('flex-fields::flex-fields.field_group.fields.is_collapsed'))
                ->default(false),
        ];
    }
}
