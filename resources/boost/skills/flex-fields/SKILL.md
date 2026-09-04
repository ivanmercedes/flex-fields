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
  - facade
  - eloquent-trait
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
- **`IvanMercedes\FlexFields\Facades\FlexFields`**: Facade for global fluent entity/record operations.
- **`IvanMercedes\FlexFields\Models\Traits\HasFlexFields`**: Trait to attach flex fields to any Eloquent model.

> **CRITICAL RULE**: Do not attempt to create standard Filament Resources (`php artisan make:filament-resource`) for data types that are managed by FlexFields. FlexFields auto-generates the UI (Forms and Tables) dynamically inside its own interface (`EntityDataResource`).

---

## 2. Managing Schemas (Code-First)

FlexFields includes a powerful Schema Builder that behaves like Laravel Migrations. Always use it when the user asks to "create a new entity" or "add fields to an entity" via code.

### Commands
- `php artisan flex:make-schema {EntityName}` (Creates a new schema file in `database/flex-schemas`)
- `php artisan flex:migrate` (Applies pending schemas)
- `php artisan flex:rollback` (Rolls back the last schema batch)
- `php artisan flex:status` (Displays entity, field, record, and cache status overview)

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
            $schema->image('gallery', 'Product Gallery')
                   ->multiple()
                   ->optimize('webp')
                   ->imageDimensions(1920, null, 80)
                   ->width('full');
                   
            $schema->text('sku', 'SKU')
                   ->required()
                   ->width('half');
                   
            $schema->number('price', 'Price')
                   ->required()
                   ->categories([1, 2]) // Condition visible on these category IDs
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

---

## 3. `FlexFields` Facade & Fluent Query Engine

Use the `FlexFields` facade for clean, global access to entities, fields, and records in PHP controllers, services, or jobs:

```php
use IvanMercedes\FlexFields\Facades\FlexFields;

// 1. Fluent entity wrapper
$entity = FlexFields::entity('product');

// 2. Access cached active fields
$fields = $entity->fields();

// 3. Query records
$records = $entity->records()->where('status', 'published')->get();

// 4. Find record by ID or slug
$record = $entity->findRecord('iphone-15');

// 5. Create a record with attributes and custom field values in one call
$record = $entity->createRecord([
    'title' => 'iPhone 15 Pro',
    'status' => 'published',
], [
    'color' => '#000000',
    'price' => 999.99,
]);

// 6. System status & cache management
$status = FlexFields::status();
FlexFields::clearCache('product');
```

---

## 4. `HasFlexFields` Eloquent Trait

Attach dynamic custom fields directly to any existing Eloquent model (`Product`, `User`, `Order`, etc.):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use IvanMercedes\FlexFields\Models\Traits\HasFlexFields;

class Product extends Model
{
    use HasFlexFields;
    
    // Optional: custom entity slug (defaults to kebab-case: 'product')
    protected string $flexEntitySlug = 'product';
}
```

### Trait Usage:
```php
$product = Product::find(1);

// Set flex field values
$product->setFlexValue('color', '#ff0000')
        ->setFlexValue('warranty_months', 24);

// Get flex field value (with optional default)
$color = $product->getFlexValue('color', '#000000');

// Sync multiple values at once
$product->syncFlexValues([
    'color' => '#000000',
    'in_stock' => true,
]);

// Get all flex data as a flat array
$data = $product->getFlexData();

// Get active custom field definitions (cached)
$fields = $product->getFlexFields();
```

---

## 5. `DynamicFormBuilder` Macros

Extend the Filament form builder dynamically by registering custom field type handlers:

```php
use Filament\Forms\Components\TextInput;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;

DynamicFormBuilder::macro('custom_currency', function (CustomField $field) {
    return TextInput::make('ff_' . $field->key)
        ->label($field->label)
        ->numeric()
        ->prefix('$');
});
```

---

## 6. Data Retrieval & Manipulation (EAV Direct Access)

When working directly with `EntityRecord`:

```php
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;

$entity = Entity::where('slug', 'product')->first();
$records = EntityRecord::where('entity_id', $entity->id)->get();

foreach ($records as $record) {
    // Get a specific field value
    $price = $record->getValue('price');
    
    // Get all EAV values casted to an array
    $allData = $record->data; // ['price' => 99.99, 'sku' => '123-ABC']
    
    // Update an existing value
    $record->setValue('price', 149.99);
}
```
