# FlexFields — ACF for Laravel + Filament

A powerful **Custom Entities & Custom Fields** plugin for [Filament](https://filamentphp.com). Think of it as **Advanced Custom Fields (ACF) for Laravel**, adapted for the Filament admin panel ecosystem.

---

## Features

| Feature | Description |
|---|---|
| **Custom Entities** | Define any data structure (like post types in WordPress) — "Products", "Employees", "Events", etc. |
| **Custom Fields** | 15+ field types: text, textarea, number, email, URL, date, datetime, toggle, select, multi-select, color, file, image, rich text, JSON, tags |
| **Drag to Reorder** | Custom fields can be reordered via drag-and-drop in the table view |
| **Dynamic Forms** | Forms for each entity are generated automatically from its field definitions |
| **Dynamic Tables** | Table columns are generated from fields marked as "show in list" |
| **Dashboard** | Visual overview of all entities, their field counts, and record counts |
| **Stats Widget** | Embeddable widget for the main Filament dashboard |
| **EAV Storage** | Field values stored efficiently using Entity-Attribute-Value pattern |
| **Field Width Control** | Fields can span full, half, or one-third of the form grid |
| **Validation Support** | Mark fields as required; extensible validation rules |
| **Per-field Settings** | Searchable, show-in-list, active/inactive toggles per field |

---

##  Installation

### 1. Register in `composer.json`

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "plugins/flex-fields",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "ivanmercedes/flex-fields": "*"
    }
}
```

```bash
composer require ivanmercedes/flex-fields --no-scripts
composer dump-autoload
```

### 2. Run the install command

```bash
php artisan flex-fields:install
php artisan migrate
```

### 3. Register in your Panel Provider

```php
// app/Providers/Filament/AdminPanelProvider.php

use IvanMercedes\FlexFields\FlexFieldsPlugin;

->plugins([
    FlexFieldsPlugin::make(),
    // or with options:
    FlexFieldsPlugin::make()
        ->showDashboardPage(true)
        ->showOverviewWidget(true),
])
```

---

##  How It Works

### Entities
An **Entity** is like a custom post type. Examples:
- `Product` (slug: `product`)
- `Employee` (slug: `employee`)
- `Event` (slug: `event`)

Each entity gets its own dashboard card, and its records are stored in a shared `ff_entity_records` table.

### Custom Fields
Each **Custom Field** belongs to an Entity and defines:
- **Type** — What kind of data it stores (text, image, date, etc.)
- **Key** — The machine-readable name (auto-generated from label)
- **Order** — Drag-and-drop sortable in the admin table
- **Width** — Full, half, or one-third grid layout
- **Behavior** — Required, searchable, shown in list, active

### Entity Records
When you add records to an entity, the form is **dynamically generated** from that entity's field definitions. Values are stored using the EAV pattern in `ff_field_values`.

### Field Values (EAV)
```
ff_entities          → defines the "type"
ff_custom_fields     → defines the "columns"
ff_entity_records    → one row per record
ff_field_values      → one row per (record × field) pair
```

---

## Database Schema

```
ff_entities
├── id, name, slug, description
├── icon, color
├── is_active, show_in_menu, menu_order
└── settings (JSON)

ff_custom_fields
├── id, entity_id (FK)
├── label, key, type
├── description, placeholder, default_value
├── options (JSON) ← for select/multiselect
├── validation_rules (JSON)
├── settings (JSON)
├── order, width
└── is_required, is_active, is_searchable, is_shown_in_list

ff_entity_records
├── id, entity_id (FK)
├── title, status, order
└── meta (JSON)

ff_field_values
├── id
├── entity_record_id (FK)
├── custom_field_id (FK)
└── value (longText)
```

---

## Supported Field Types

| Key | Label | Notes |
|---|---|---|
| `text` | Text (single line) | |
| `textarea` | Textarea (multi-line) | |
| `number` | Number | Cast to float |
| `email` | Email | Validated |
| `url` | URL | Validated |
| `date` | Date | Date picker |
| `datetime` | Date & Time | DateTime picker |
| `boolean` | Toggle (Yes/No) | Cast to bool |
| `select` | Select (dropdown) | Requires options |
| `multiselect` | Multi-select | Stored as JSON array |
| `color` | Color picker | Hex value |
| `file` | File upload | Stored path |
| `image` | Image upload | With image editor |
| `richtext` | Rich text editor | HTML stored |
| `json` | JSON / Code | Stored as raw JSON |
| `tags` | Tags | Stored as JSON array |

---

## Usage in Code

```php
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;

// Get an entity
$entity = Entity::where('slug', 'product')->first();

// Get all records for an entity
$records = EntityRecord::where('entity_id', $entity->id)->get();

// Get field value on a record
$price = $records[0]->getValue('price');
$name  = $records[0]->getValue('product_name');

// Get all values as array
$data = $records[0]->data; // ['price' => 99.99, 'product_name' => 'Widget']

// Set a value
$records[0]->setValue('price', 149.99);
```

---

## Customizing

### Add custom field types

In `config/flex-fields.php`:
```php
'field_types' => [
    // ... existing types ...
    'my_custom' => 'My Custom Type',
],
```

Then extend `DynamicFormBuilder::makeComponent()` to handle your new type.

### Change navigation group

```php
// config/flex-fields.php
'navigation_group' => 'Content',
```

### Publish views for full customization

```bash
php artisan vendor:publish --tag=flex-fields-views
```

---

## Navigation Structure

```
FlexFields (group)
├── Dashboard       → Overview of all entities and stats
├── Entities        → Create/edit entity types
├── Custom Fields   → Manage fields per entity (drag to reorder)
└── Entity Data     → CRUD records for any entity
```

---

## Plugin Options

```php
FlexFieldsPlugin::make()
    ->showDashboardPage(true)   // Show/hide the FlexFields Dashboard page
    ->showOverviewWidget(true)  // Show/hide the stats widget on main dashboard
```

---

## File Structure

```
plugins/flex-fields/
├── composer.json
├── config/
│   └── flex-fields.php
├── database/
│   └── migrations/
│       ├── ..._create_ff_entities_table.php
│       ├── ..._create_ff_custom_fields_table.php
│       └── ..._create_ff_records_and_values_tables.php
├── resources/
│   ├── lang/en/flex-fields.php
│   └── views/filament/pages/dashboard.blade.php
└── src/
    ├── FlexFieldsServiceProvider.php
    ├── FlexFieldsPlugin.php
    ├── Commands/
    │   └── InstallFlexFieldsCommand.php
    ├── Filament/
    │   ├── Pages/FlexFieldsDashboard.php
    │   └── Widgets/EntitiesOverviewWidget.php
    ├── Models/
    │   ├── Entity.php
    │   ├── CustomField.php
    │   ├── EntityRecord.php
    │   └── FieldValue.php
    ├── Resources/
    │   ├── EntityResource.php
    │   ├── EntityResource/Pages/EntityPages.php
    │   ├── CustomFieldResource.php
    │   ├── CustomFieldResource/Pages/CustomFieldPages.php
    │   ├── EntityDataResource.php
    │   └── EntityDataResource/Pages/EntityDataPages.php
    └── Support/
        ├── DynamicFormBuilder.php
        └── DynamicTableBuilder.php
```

---

## Requirements

- PHP 8.1+
- Laravel 12+
- Filament 4.x

---

## License

MIT
