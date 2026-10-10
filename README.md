# FlexFields — Dynamic Entities & Custom Fields for Filament

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ivanmercedes/flex-fields.svg?style=flat-square)](https://packagist.org/packages/ivanmercedes/flex-fields)
[![Total Downloads](https://img.shields.io/packagist/dt/ivanmercedes/flex-fields.svg?style=flat-square)](https://packagist.org/packages/ivanmercedes/flex-fields)
[![License](https://img.shields.io/packagist/l/ivanmercedes/flex-fields.svg?style=flat-square)](https://packagist.org/packages/ivanmercedes/flex-fields)
[![PHP version support](https://img.shields.io/packagist/php-v/ivanmercedes/flex-fields.svg?style=flat-square)](https://packagist.org/packages/ivanmercedes/flex-fields)
[![Run Laravel Pint](https://github.com/ivanmercedes/flex-fields/actions/workflows/laravel-pint.yml/badge.svg)](https://github.com/ivanmercedes/flex-fields/actions/workflows/laravel-pint.yml)


**FlexFields** brings the power of dynamic schemas to Laravel and the [Filament](https://filamentphp.com) admin panel. Think of it as **Advanced Custom Fields (ACF)** adapted to the Filament ecosystem. It allows you to create Custom Entities (like post types: "Products", "Employees", "Events") and attach customizable fields to them on the fly—without having to modify your database schema every time.

---

## Features

- **Custom Entities:** Define any data structure (like post types) without touching database migrations.
- **20 Custom Field Types:** Support for text, textarea, number, email, URL, date, datetime, boolean/toggle, select, multiselect, color, file, image, rich text, JSON, tags, dynamic repeaters, phone (with masks), structured addresses and relationships to records of other entities.
- **Advanced Field Behavior:** Conditional visibility (`showWhen`), translatable fields (`{"en": "Hello", "es": "Hola"}`) and a visual validation rule builder.
- **Field Groups / Sections:** Organize fields into labeled, collapsible form sections.
- **CSV Export & CSV/Excel Import:** Export records (all, filtered or selected) and bulk import with a downloadable template and a validated preview before confirming.
- **Record History / Audit Log:** Who changed what and when, with a visual diff and one-click version restore.
- **Headless REST API:** Optional `GET/POST/PUT/DELETE /api/flex/{entity}` endpoints with Laravel Sanctum, token abilities and customizable JSON transformers.
- **Permissions per Entity:** Gate-based permissions per entity (compatible with Filament Shield / spatie-permission), enforced in the panel, the API and AI tools.
- **Spatie Media Library Integration:** Optionally store image and file fields with `spatie/laravel-medialibrary`.
- **Laravel AI SDK Integration:** First-party tools for AI agents to introspect schemas, read, create, mutate, publish content, and safely execute destructive actions with human approvals.
- **`HasFlexFields` Eloquent Trait:** Attach dynamic flex fields directly to any existing model (`Product`, `User`, etc.).
- **`FlexFields` Facade:** Fluent global access: `FlexFields::entity('product')->records()`, `createRecord()`, `status()`, `clearCache()`.
- **Field Caching:** High-performance active field caching with automatic invalidation on save/delete.
- **Form Builder Macros:** Register custom field component handlers via `DynamicFormBuilder::macro(...)`.
- **`flex:status` Artisan Command:** Display entity, field, record, multi-tenancy, and cache configuration overview in the terminal.
- **Multi-Tenancy Support:** Full compatibility with Filament's multi-tenancy system, allowing scoped entities, fields, and records per tenant.
- **Dynamic Forms & Tables:** Forms for each entity are generated automatically from its field definitions. Tables are populated dynamically with fields marked as "Show in list".
- **Drag-and-Drop Reordering:** Easily rearrange custom fields within an entity.
- **Entity Categories:** Create hierarchical categories and subcategories per entity, and categorize your records easily.
- **EAV Storage:** Robust and scalable Entity-Attribute-Value storage pattern natively adapted for Eloquent.
- **Built-in Dashboard & Widget:** Visual overview of all entities, fields, and records, plus an embeddable stats widget.

---

## See it in Action

### 1. The Global Dashboard
Visual overview of all your dynamic entities and custom fields.
![Dashboard Screenshot](docs/dashboard.png)

### 2. Entity Management
Define your custom data types (like "Craft Beer" or "Brewery").
![Entities Screenshot](docs/entities.png)

### 3. Custom Fields Builder
Easily add, configure, and reorder fields for each entity.
![Custom Fields Screenshot](docs/custom-fields.png)

### 4. Dynamic Forms & Records
Create entries using beautiful, dynamically generated forms powered by Filament.
![Craft Beer Screenshot](docs/craft-beer.png)

![Form Screenshot](docs/form-screenshot.png)

---

## Requirements

- PHP 8.3 or higher
- Filament 4.x / 5.x

---

## Installation

You can install the package via composer:

```bash
composer require ivanmercedes/flex-fields
```

After requiring the package, run the installation command. This will publish the necessary migrations and configuration files:

```bash
php artisan flex-fields:install
```

Then, run the migrations:

```bash
php artisan migrate
```

### Register the Plugin

Add the `FlexFieldsPlugin` to your Filament panel configuration (usually inside `app/Providers/Filament/AdminPanelProvider.php`):

```php
use IvanMercedes\FlexFields\FlexFieldsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            FlexFieldsPlugin::make()
                ->showDashboardPage(true)
                ->showOverviewWidget(true)
                ->showEntitiesInMenu(true), // Globally toggle entities in the sidebar
        ]);
}
```

---

## How It Works

### Entities
An **Entity** represents a custom data type. For example: `Product` (slug: `product`), `Employee`, or `Event`. Each entity manages its own isolated form, with records seamlessly stored in a shared `ff_entity_records` table.

### Custom Fields
A **Custom Field** is attached to an Entity. It defines the type of data, a unique machine-readable key (auto-generated from the label), layout formatting (order, width), and behavior toggles (searchable, required, shown in list).

### Entity Categories
Entities support isolated **Categories and Subcategories**. You can build hierarchical taxonomies specific to an entity and assign them dynamically to your records.

### Entity Records
When adding records to an entity, the form elements and structure are dynamically built via the Filament form builder using the definitions from your custom fields. All input is securely stored using the EAV pattern in `ff_field_values`.

### Field Groups
Fields can be organized into **Field Groups**: labeled, collapsible sections of the record form, managed from the entity edit page.

### Record History
Every change to a record is stored as a revision (snapshot + diff + user). The **History** tab of a record lets you inspect the changes and restore any previous version.

---

## Guides

- [Upgrade Guide](UPGRADE.md) — **read before upgrading** (run `php artisan migrate`)
- [Advanced Field Types](docs/field-types.md) — relationship, conditional visibility, validation builder, phone, address, translatable fields
- [Data Management](docs/data-management.md) — CSV export, CSV/Excel import, field groups, record history
- [Headless REST API](docs/rest-api.md) — endpoints, Sanctum, tenancy, transformers
- [Permissions & Integrations](docs/permissions.md) — per-entity permissions, Filament Shield, Spatie Media Library
- [Schema Builder](docs/schema-builder.md) — code-first entities
- [Facade & Developer Experience](docs/facade_usage.md)
- [Laravel AI SDK Integration](docs/ai-integration.md)

---

## Schema Builder (Code-First Entities)

FlexFields includes a Schema Builder that allows you to define your Entities and Custom Fields programmatically, similar to Laravel migrations.

```bash
php artisan flex:make-schema Product
php artisan flex:migrate
php artisan flex:rollback
```

For full details, read the [Schema Builder Documentation](docs/schema-builder.md).

---

## Developer Experience & Facade Usage

### `FlexFields` Facade
```php
use IvanMercedes\FlexFields\Facades\FlexFields;

// Fluent entity access
$entity = FlexFields::entity('product');
$fields = $entity->fields(); // Cached active fields
$records = $entity->records()->where('status', 'published')->get();

// Create record with field values
$record = $entity->createRecord([
    'title' => 'Sample Product',
], [
    'price' => 99.99,
    'color' => '#00ff00',
]);
```

### `HasFlexFields` Trait
Attach flex fields directly to any model:

```php
use Illuminate\Database\Eloquent\Model;
use IvanMercedes\FlexFields\Models\Traits\HasFlexFields;

class Product extends Model
{
    use HasFlexFields;
}

$product = Product::find(1);
$product->setFlexValue('price', 199.99);
$price = $product->getFlexValue('price');
```

### System Status Command
```bash
php artisan flex:status
```

### Permissions Command
```bash
php artisan flex:permissions          # list per-entity permissions
php artisan flex:permissions --sync   # create them with spatie/laravel-permission (Filament Shield)
```

For full details, read the [Facade & Developer Experience Documentation](docs/facade_usage.md).

---

## Laravel AI SDK Integration (AI-Ready)

FlexFields includes native Tools and a complete out-of-the-box Agent for the official **Laravel AI SDK** (`laravel/ai`), enabling conversational agents to discover schemas, manage entities, and perform CRUD and publishing operations on records.

### Interactive CLI:
```bash
php artisan flex:agent
```

### Pre-built Agent:
```php
use IvanMercedes\FlexFields\Ai\Agents\FlexFieldsAssistant;

$agent = new FlexFieldsAssistant;
$response = $agent->prompt('Show schema for products');
```

Or plug individual tool groups into your own custom agents:

```php
use IvanMercedes\FlexFields\Ai\FlexFieldsAi;
use Laravel\Ai\Agent;

class ContentAgent extends Agent
{
    public function tools(): array
    {
        return [
            // All tools or filtered by categories: read, structure, content, publishing, destructive
            ...FlexFieldsAi::tools(['read', 'content', 'publishing']),
        ];
    }
}
```

For full details, read the [Laravel AI SDK Integration Documentation](docs/ai-integration.md).

---

## Headless REST API

Enable the optional JSON API to use your entities from any frontend or mobile app:

```env
FLEX_FIELDS_API_ENABLED=true
```

```bash
curl -H "Authorization: Bearer $TOKEN" https://your-app.test/api/flex/products?status=published
```

All endpoints are protected by Laravel Sanctum by default and reuse the same domain services (validation, history, tenancy, permissions) as the admin panel. For full details, read the [REST API Documentation](docs/rest-api.md).

---

## Customizing

### Manage Config Settings

To customize the default settings, publish the configuration file to your project:

```bash
php artisan vendor:publish --tag="flex-fields-config"
```

In `config/flex-fields.php`, you can configure Multi-Tenancy, Field Caching, Upload path patterns, and more:

```php
return [
    'tenancy' => [
        'enabled' => true,
        'tenant_model' => App\Models\Team::class,
        'tenant_column' => 'tenant_id',
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 86400,
    ],
    
    'navigation_group' => 'Content',
];
```

---

## Navigation Structure overview

By default, the plugin registers the following navigation resources under the `FlexFields` group:

```text
FlexFields (group)
├── Dashboard       → Overview of all active entities and stats
├── Entities        → Create, config, and manage entity types
├── Custom Fields   → Manage fields logically grouped per entity
└── Entity Data     → Add or edit entries/records for your entities
```

---

## Database Architecture

- **`ff_entities`**: Defines the "type" of the data grouping (e.g., Products, Services).
- **`ff_custom_fields`**: Serves as the "columns/attributes" logic mapped to an entity.
- **`ff_entity_categories`**: Stores hierarchical categories specifically scoped to an entity.
- **`ff_entity_records`**: A master identifier that holds one row per recorded entry.
- **`ff_entity_record_category`**: Pivot table mapping records to their assigned categories.
- **`ff_field_values`**: Holds the individual data values mapped. Effectively one row per (record × field) pair.
- **`ff_field_groups`**: Labeled form sections that group the custom fields of an entity.
- **`ff_record_revisions`**: Audit log of records (event, user, full snapshot and diff per change).

---

## Roadmap

All the scheduled roadmap milestones (Data Management, Advanced Field Types, Headless & Integrations) are implemented. Check out the [ROADMAP.md](ROADMAP.md) for details and the ideas under consideration.

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
