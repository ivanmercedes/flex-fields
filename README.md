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
- **17+ Custom Field Types:** Support for text, textarea, number, email, URL, date, datetime, boolean/toggle, select, multiselect, color, file, image, rich text, JSON, tags, and dynamic repeaters.
 - **Multi-Tenancy Support:** Full compatibility with Filament's multi-tenancy system, allowing scoped entities, fields, and records per tenant.
- **Dynamic Forms & Tables:** Forms for each entity are generated automatically from its field definitions. Tables are populated dynamically with fields marked as "Show in list".
- **Drag-and-Drop Reordering:** Easily rearrange custom fields within an entity.
- **Entity Categories:** Create hierarchical categories and subcategories per entity, and categorize your records easily.
- **EAV Storage:** Robust and scalable Entity-Attribute-Value storage pattern natively adapted for Eloquent.
- **Built-in Dashboard & Widget:** Visual overview of all entities, fields, and records, plus an embeddable stats widget.
- **Field Width & Validation:** Define grid widths (full, half, one-third) and toggle constraints (required, searchable, active) per field.

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
                ->showOverviewWidget(true),
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

## Usage in Code

You can easily interact with entities and their records directly from your models:

```php
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;

// Retrieve an entity by its slug
$entity = Entity::where('slug', 'product')->first();

// Get all records belonging to this entity
$records = EntityRecord::where('entity_id', $entity->id)->get();

if ($records->isNotEmpty()) {
    $firstRecord = $records->first();

    // Retrieve specific custom field values
    $price = $firstRecord->getValue('price');
    $name  = $firstRecord->getValue('product_name');

    // Retrieve all values as an array
    // Example: ['price' => 99.99, 'product_name' => 'Awesome Widget']
    $data = $firstRecord->data; 

    // Retrieve the record's assigned categories
    $categories = $firstRecord->categories;

    // Update or set a new value programmatically
    $firstRecord->setValue('price', 149.99);
}
```

---

## Customizing

### Manage Config Settings

To customize the default settings, publish the configuration file to your project:

```bash
php artisan vendor:publish --tag="flex-fields-config"
```

In `config/flex-fields.php`, you can configure Multi-Tenancy, change the default navigation group name, and more:

```php
return [
    'tenancy' => [
        'enabled' => true,
        'tenant_model' => App\Models\Team::class,
        'tenant_column' => 'tenant_id',
    ],
    
    'navigation_group' => 'Content',
];
```


### Override Views

If you want to customize the built-in views (like the main dashboard page), publish them to your project:

```bash
php artisan vendor:publish --tag="flex-fields-views"
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

For those curious about the underlying EAV (Entity-Attribute-Value) structure:

- **`ff_entities`**: Defines the "type" of the data grouping (e.g., Products, Services).
- **`ff_custom_fields`**: Serves as the "columns/attributes" logic mapped to an entity.
- **`ff_entity_categories`**: Stores hierarchical categories specifically scoped to an entity.
- **`ff_entity_records`**: A master identifier that holds one row per recorded entry.
- **`ff_entity_record_category`**: Pivot table mapping records to their assigned categories.
- **`ff_field_values`**: Holds the individual data values mapped. Effectively one row per (record × field) pair.

---

## Roadmap

Curious about what's coming next? Check out the [ROADMAP.md](ROADMAP.md) for planned features and upcoming versions.

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
