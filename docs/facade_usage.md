# FlexFields Facade & Developer Experience Guide

The `FlexFields` facade and `HasFlexFields` Eloquent trait provide fluent global access to custom fields and dynamic entities in PHP code.

---

## 1. `FlexFields` Facade

### Accessing Entities & Records
```php
use IvanMercedes\FlexFields\Facades\FlexFields;

// Get fluent query wrapper for an entity (by slug, ID, or Entity model)
$entity = FlexFields::entity('product');

// Get cached active fields for the entity
$fields = $entity->fields();

// Access records query builder
$records = $entity->records()->where('status', 'published')->get();

// Find record by ID or slug
$record = $entity->findRecord('iphone-15');

// Create a new record with custom field values
$record = $entity->createRecord([
    'title' => 'iPhone 15 Pro',
    'status' => 'published',
], [
    'color' => '#000000',
    'price' => 999.99,
    'storage' => '256GB',
]);
```

### System Status & Caching
```php
// Get system statistics summary
$status = FlexFields::status();

// Clear active field caches
FlexFields::clearCache('product'); // single entity
FlexFields::clearCache();          // all entities
```

---

## 2. `HasFlexFields` Eloquent Trait

Attach dynamic flex fields to any existing Eloquent model (`Product`, `User`, `Order`, etc.):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use IvanMercedes\FlexFields\Models\Traits\HasFlexFields;

class Product extends Model
{
    use HasFlexFields;

    // Optional: override entity slug (defaults to kebab-case of class name: 'product')
    protected string $flexEntitySlug = 'product';
}
```

### Usage:
```php
$product = Product::find(1);

// Set flex field values
$product->setFlexValue('color', '#ff0000')
        ->setFlexValue('warranty_months', 24);

// Get a flex field value
$color = $product->getFlexValue('color', '#000000');

// Sync multiple values at once
$product->syncFlexValues([
    'color' => '#000000',
    'in_stock' => true,
]);

// Get all flex field values as flat array
$data = $product->getFlexData();

// Get active CustomField definitions (cached)
$fields = $product->getFlexFields();
```

---

## 3. Extending `DynamicFormBuilder` Macros

Register custom field component handlers dynamically:

```php
use Filament\Forms\Components\TextInput;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;

// Register custom macro for a new field type
DynamicFormBuilder::macro('custom_currency', function (CustomField $field) {
    return TextInput::make('ff_' . $field->key)
        ->label($field->label)
        ->numeric()
        ->prefix('$');
});
```

---

## 4. `flex:status` Artisan Command

Run in terminal to inspect entities, custom fields, records, multi-tenancy, and field cache configuration:

```bash
php artisan flex:status
```
