<?php

declare(strict_types=1);
use App\Models\Team;

return [
    /*
    |--------------------------------------------------------------------------
    | FlexFields — Custom Entities & Fields for Filament
    |--------------------------------------------------------------------------
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Support
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        'enabled' => false,
        'tenant_model' => Team::class,
        'tenant_column' => 'tenant_id',

        // Specify which models should be scoped to a tenant.
        'is_tenant_aware' => [
            'entities' => true,
            'custom_fields' => true,
            'categories' => true,
            'records' => true,
            'field_values' => true,
        ],
    ],

    /*
    | Field types available when creating custom fields.
    | Each type maps to a Filament form component and a cast.
    */
    'field_types' => [
        'text' => 'flex-fields::flex-fields.field_types.text',
        'textarea' => 'flex-fields::flex-fields.field_types.textarea',
        'number' => 'flex-fields::flex-fields.field_types.number',
        'email' => 'flex-fields::flex-fields.field_types.email',
        'url' => 'flex-fields::flex-fields.field_types.url',
        'date' => 'flex-fields::flex-fields.field_types.date',
        'datetime' => 'flex-fields::flex-fields.field_types.datetime',
        'boolean' => 'flex-fields::flex-fields.field_types.boolean',
        'select' => 'flex-fields::flex-fields.field_types.select',
        'multiselect' => 'flex-fields::flex-fields.field_types.multiselect',
        'color' => 'flex-fields::flex-fields.field_types.color',
        'file' => 'flex-fields::flex-fields.field_types.file',
        'image' => 'flex-fields::flex-fields.field_types.image',
        'richtext' => 'flex-fields::flex-fields.field_types.richtext',
        'json' => 'flex-fields::flex-fields.field_types.json',
        'tags' => 'flex-fields::flex-fields.field_types.tags',
        'repeater' => 'flex-fields::flex-fields.field_types.repeater',
    ],

    /*
    | Navigation group for the plugin resources in the sidebar.
    */
    'navigation_group' => 'flex-fields::flex-fields.navigation.group',

    /*
    | Icons (uses Heroicons v2 names)
    */
    'icons' => [
        'entity' => 'heroicon-o-cube',
        'custom_field' => 'heroicon-o-variable',
        'entity_data' => 'heroicon-o-table-cells',
        'dashboard' => 'heroicon-o-squares-2x2',
    ],
];
