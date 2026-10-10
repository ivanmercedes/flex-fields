<?php

declare(strict_types=1);
use App\Models\Team;
use IvanMercedes\FlexFields\Http\Resources\EntitySchemaResource;
use IvanMercedes\FlexFields\Http\Resources\RecordResource;
use IvanMercedes\FlexFields\Models\EntityRecord;

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
        'phone' => 'flex-fields::flex-fields.field_types.phone',
        'address' => 'flex-fields::flex-fields.field_types.address',
        'relationship' => 'flex-fields::flex-fields.field_types.relationship',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap the record model for your own subclass of EntityRecord. This is how
    | the Spatie Media Library integration is enabled (see 'media' below):
    | 'record' => \IvanMercedes\FlexFields\Models\MediaEntityRecord::class
    |
    */
    'models' => [
        'record' => EntityRecord::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Translatable Fields
    |--------------------------------------------------------------------------
    |
    | Locales offered for fields marked as "translatable". Values are stored as
    | {"en": "Hello", "es": "Hola"}. The first locale is the default one.
    |
    */
    'translations' => [
        'locales' => ['en'],
        'fallback_locale' => null, // null = first locale in the list
    ],

    /*
    |--------------------------------------------------------------------------
    | Record History / Audit Log
    |--------------------------------------------------------------------------
    |
    | Every create/update/delete/restore of a record stores a revision with a
    | full snapshot, a diff and the user who made the change.
    |
    */
    'history' => [
        'enabled' => env('FLEX_FIELDS_HISTORY_ENABLED', true),
        'max_revisions' => (int) env('FLEX_FIELDS_HISTORY_MAX_REVISIONS', 50), // per record, 0 = unlimited
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV / Excel Import & Export
    |--------------------------------------------------------------------------
    */
    'import_export' => [
        'disk' => env('FLEX_FIELDS_IMPORT_DISK', 'local'),
        'directory' => 'flex-fields-imports',
        'max_rows' => (int) env('FLEX_FIELDS_IMPORT_MAX_ROWS', 5000),
        'csv_delimiter' => ',',
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
    |--------------------------------------------------------------------------
    | Headless REST API
    |--------------------------------------------------------------------------
    |
    | Optional endpoints: GET/POST /api/flex/{entity}, GET/PUT/DELETE
    | /api/flex/{entity}/{record}, GET /api/flex/{entity}/schema and
    | GET /api/flex/entities. Protected by Laravel Sanctum by default.
    |
    */
    'api' => [
        'enabled' => env('FLEX_FIELDS_API_ENABLED', false),
        'prefix' => env('FLEX_FIELDS_API_PREFIX', 'api/flex'),
        'middleware' => ['api', 'auth:sanctum'],
        'allowed_entities' => null, // null = all entities, or ['products', 'posts']
        'per_page' => 25,
        'max_per_page' => 100,

        // Sanctum token abilities required per operation (ignored for non-token auth).
        'token_abilities' => [
            'read' => 'flex:read',
            'write' => 'flex:write',
            'delete' => 'flex:delete',
        ],

        // Invokable class receiving the Request and returning the tenant key.
        // Required when multi-tenancy is enabled. The API never trusts a
        // tenant supplied by the client.
        'tenant_resolver' => null,

        // JSON transformers (must extend the package defaults).
        'resources' => [
            'record' => RecordResource::class,
            'entity' => EntitySchemaResource::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions / Policies per Entity
    |--------------------------------------------------------------------------
    |
    | When enabled, every entity gets its own set of permissions
    | (e.g. "view_any_flex_products", "create_flex_products"), checked through
    | Laravel's Gate — compatible with spatie/laravel-permission & Filament
    | Shield. Structural resources (entities, fields, groups, categories)
    | require the 'structure_permission'.
    |
    */
    'permissions' => [
        'enabled' => env('FLEX_FIELDS_PERMISSIONS_ENABLED', false),
        'format' => '{ability}_flex_{entity}',
        'abilities' => [
            'view_any', 'view', 'create', 'update', 'delete',
            'restore', 'force_delete', 'publish', 'import', 'export',
        ],
        'structure_permission' => 'manage_flex_structure',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Library
    |--------------------------------------------------------------------------
    |
    | 'native' stores file paths in field values (default).
    | 'spatie' uses spatie/laravel-medialibrary through Filament's
    | SpatieMediaLibraryFileUpload (requires the record model above to be
    | MediaEntityRecord and filament/spatie-laravel-media-library-plugin).
    |
    */
    'media' => [
        'driver' => env('FLEX_FIELDS_MEDIA_DRIVER', 'native'),
        'collection_prefix' => 'ff_',
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
