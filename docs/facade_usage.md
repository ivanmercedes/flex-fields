# FlexFields Facade Usage Guide

The `FlexFields` facade provides a convenient way to interact with custom fields and entities in your Laravel application.

## Basic Usage

### Get all active fields
Retrieves all custom fields that are marked as active in the database.
```php
use IvanMercedes\FlexFields\Facades\FlexFields;

$fields = FlexFields::fields();
```

### Get fields by key
```php
$fields = FlexFields::get('price');
```

---

## Model Integration

### Get fields for a specific model class
To use this, make sure you have an `Entity` where `model_class` is set to your model's full namespace.
```php
use App\Models\Product;

$productFields = FlexFields::getByModel(Product::class);
```

### Get fields for a model instance
```php
$product = Product::find(1);
$fields = FlexFields::getByModelInstance($product);
```

---

## Filtering by Type

### Get all fields of a specific type
```php
$textFields = FlexFields::getByType('text');
$selectFields = FlexFields::getByType('select');
```

### Get fields of a type for a specific model
```php
$productDateFields = FlexFields::getByTypeAndModel('date', Product::class);
```

---

## Searching by Name/Label

### Search fields by label or key
```php
$fields = FlexFields::getByName('Description');
```

### Search fields for a specific model
```php
$fields = FlexFields::getByNameAndModel('Price', Product::class);
```

---

## Working with the Collection
All methods return a `FlexFieldCollection` which extends Eloquent's Collection with extra helpers:

```php
$fields = FlexFields::getByModel(Product::class);

$searchable = $fields->searchable();
$inList = $fields->shownInList();
$sorted = $fields->sorted();

// Get a single field by key from the collection
$priceField = $fields->getByKey('price');
```
