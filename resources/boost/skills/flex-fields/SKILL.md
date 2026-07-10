---
name: FlexFields
description: ACF-like Custom Entities & Custom Fields plugin for Laravel Filament
compatible_agents:
  - Claude Code
  - Cursor
  - GitHub Copilot
tags:
  - laravel
  - filament
  - flex-fields
  - schema-builder
---

# FlexFields Skill for AI Assistants

This project uses the `ivanmercedes/flex-fields` package, which provides Advanced Custom Fields (ACF) style functionality natively for Laravel Filament.

**As an AI agent, use this document to understand how to interact with FlexFields.**

---

## 1. Core Architecture

FlexFields uses an EAV (Entity-Attribute-Value) architecture natively integrated with Filament:
- **`IvanMercedes\FlexFields\Models\Entity`**: Represents a custom data type (like a Post Type in WordPress). e.g., `Product`, `Event`.
- **`IvanMercedes\FlexFields\Models\CustomField`**: Represents a field definition assigned to an Entity.
- **`IvanMercedes\FlexFields\Models\EntityRecord`**: Represents an entry for an Entity.
- **`IvanMercedes\FlexFields\Models\EntityCategory`**: Hierarchical taxonomies for entities.

> **CRITICAL RULE**: Do not attempt to create standard Filament Resources (`php artisan make:filament-resource`) for data types that are managed by FlexFields. FlexFields auto-generates the UI (Forms and Tables) dynamically inside its own interface (`EntityDataResource`).

---

## 2. Managing Schemas (Code-First)

FlexFields includes a powerful Schema Builder that behaves like Laravel Migrations. Always use it when the user asks to "create a new entity" or "add fields to an entity" via code.

### Commands
- `php artisan flex:make-schema {EntityName}` (Creates a new schema file in `database/flex-schemas`)
- `php artisan flex:migrate` (Applies pending schemas)
- `php artisan flex:rollback` (Rolls back the last schema batch)

### Schema Builder Syntax
When generating a schema file inside `database/flex-schemas`, use the `Flex` facade:

```php
use IvanMercedes\FlexFields\Facades\Flex;
use IvanMercedes\FlexFields\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Flex::create('product', function (Blueprint $schema) {
            $schema->description('Manage our product catalog')
                   ->icon('heroicon-o-shopping-bag')
                   ->entityColor('#3b82f6')
                   ->showInMenu(true);

            // Adding fields
            $schema->text('sku', 'SKU')
                   ->required()
                   ->width('half');
                   
            $schema->number('price', 'Price')
                   ->required()
                   ->width('half');
                   
            $schema->rich('description', 'Description')
                   ->width('full');
                   
            $schema->select('status', 'Product Status')
                   ->options([
                       'active' => 'Active',
                       'draft' => 'Draft',
                   ])
                   ->default('draft');
                   
            // Defining a Repeater Field (Nested Sub-fields)
            $schema->repeater('features', 'Product Features')
                   ->schema(function (Blueprint $table) {
                       $table->text('feature_title', 'Feature Title')
                             ->width('full')
                             ->required();
                             
                       $table->text('feature_icon', 'Icon Name')
                             ->width('half');
                   });
        });
    }

    public function down(): void
    {
        Flex::drop('product');
    }
};
```

### Available Field Types in Blueprint
- `$schema->text()`, `->textarea()`, `->rich()`, `->number()`, `->boolean()`, `->date()`, `->datetime()`
- `$schema->select()`, `->multiselect()`, `->tags()`
- `$schema->image()`, `->file()`, `->color()`, `->json()`
- `$schema->repeater()` (requires `->schema(function(Blueprint $table) { ... })`)

### Available Field Modifiers
- `->required(bool)`, `->placeholder(string)`, `->description(string)`, `->default(mixed)`
- `->options(array)` (for select/multiselect)
- `->width(string)` (accepts `'full'`, `'half'`, `'third'`)
- `->searchable(bool)`, `->showInList(bool)`, `->active(bool)`

---

## 3. Data Retrieval & Manipulation (EAV)

When writing business logic (Controllers, Jobs, APIs), interact with `EntityRecord` directly.

```php
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;

$entity = Entity::where('slug', 'product')->first();
$records = EntityRecord::where('entity_id', $entity->id)->get();

foreach ($records as $record) {
    // 1. Get a specific field value natively
    $price = $record->getValue('price');
    
    // 2. Get all EAV values casted to a clean associative array
    $allData = $record->data; // ['price' => 99.99, 'sku' => '123-ABC']
    
    // 3. Update an existing value
    $record->setValue('price', 149.99);
}
```

> **Repeater Data Format**: Repeater field values are returned as JSON-decoded Arrays. Note that the dynamic form builder internally prefixes nested repeater keys with `ff_` (e.g. `ff_feature_title`) in the UI context, but they are stored natively as JSON.
