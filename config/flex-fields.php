<?php

declare(strict_types=1);
use App\Models\Team;

return [
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
    |--------------------------------------------------------------------------
    | Uploads Configuration
    |--------------------------------------------------------------------------
    |
    | Configure storage disk, file visibility, and dynamic directory structure.
    |
    | Supported placeholders for 'directory_pattern':
    | - {tenant_id}     : Current tenant ID (if tenancy is enabled/active)
    | - {tenant_slug}   : Current tenant slug or identifier
    | - {tenant_key}    : Fallback: tenant_slug or tenant_id
    | - {entity_id}     : Entity ID
    | - {entity_slug}   : Entity slug (e.g., 'productos')
    | - {field_key}     : Custom field key (e.g., 'galeria_fotos')
    | - {field_label}   : Custom field label slugified (e.g., 'galeria-de-fotos')
    | - {year}          : Current year (YYYY)
    | - {month}         : Current month (MM)
    |
    | Visibility options: 'public', 'private'
    | Tenant key attribute: attribute on tenant model to use for {tenant_slug} (e.g. 'slug', 'id', 'name')
    */
    'uploads' => [
        'disk' => env('FLEX_FIELDS_DISK', 'public'),
        'visibility' => env('FLEX_FIELDS_VISIBILITY', 'public'),
        'directory_pattern' => env('FLEX_FIELDS_DIRECTORY_PATTERN', 'flex-fields/{tenant_slug}/{entity_slug}/{field_key}'),
        'tenant_key_attribute' => env('FLEX_FIELDS_TENANT_KEY_ATTRIBUTE', 'slug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field & Schema Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Caching active fields per entity significantly reduces database lookups
    | during form rendering. Cache is automatically invalidated when fields
    | or entities are created, updated, or deleted.
    |
    */
    'cache' => [
        'enabled' => env('FLEX_FIELDS_CACHE_ENABLED', true),
        'store' => env('FLEX_FIELDS_CACHE_STORE', null),
        'ttl' => (int) env('FLEX_FIELDS_CACHE_TTL', 86400), // 24 hours in seconds
        'prefix' => 'flex_fields_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel AI SDK Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for FlexFields AI Tools when used with Laravel AI agents.
    |
    */
    'ai' => [
        'enabled' => env('FLEX_FIELDS_AI_ENABLED', true),
        'require_approvals' => env('FLEX_FIELDS_AI_REQUIRE_APPROVALS', true),
        'read_only' => env('FLEX_FIELDS_AI_READ_ONLY', false),
        'allowed_entities' => null, // null means all entities allowed, or ['slug-1', 'slug-2']
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
