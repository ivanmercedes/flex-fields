# FlexFields Skill

This project uses the `ivanmercedes/flex-fields` package to create dynamic custom entities and custom fields inside Laravel Filament.

## Core Concepts
1. **Entities**: Similar to Custom Post Types. Defined programmatically using the Schema Builder or via the Filament UI. Stored in `ff_entities`.
2. **Custom Fields**: Attributes attached to Entities. Supports many types including Text, Select, Repeater, RichText, File, Image, etc. Stored in `ff_custom_fields`.
3. **Records**: The actual data entries for Entities. Stored using an EAV (Entity-Attribute-Value) pattern in `ff_entity_records` and `ff_field_values`.

## Artisan Commands
- `php artisan flex:make-schema {EntityName}` - Creates a new schema file in `database/flex-schemas`.
- `php artisan flex:migrate` - Applies schema files to the database.
- `php artisan flex:rollback` - Reverts the last schema migration batch.

## Interacting with Data
When writing code for the user, remember they can access entity records like this:

```php
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;

// Get an entity by slug
$entity = Entity::where('slug', 'product')->first();

// Get records
$records = EntityRecord::where('entity_id', $entity->id)->get();

// Get a specific value using EAV
$price = $record->getValue('price');

// Update a specific value using EAV
$record->setValue('price', 99.99);
```

## Repeaters
If the user asks about repeater fields, remember they act as mini schema builders that support structured sub-fields, storing data as JSON. Repeater sub-field keys are prefixed with `ff_` in the generated Filament form for consistency.
