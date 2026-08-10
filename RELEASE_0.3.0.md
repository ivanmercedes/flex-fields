# Release 0.3.0 — Developer Experience Suite, Caching & HasFlexFields Trait

## What's New in 0.3.0

We are excited to announce `0.3.0` of `ivanmercedes/flex-fields`! This release focuses on bringing a first-class Developer Experience when working with FlexFields directly in PHP code, alongside active field caching, fluent facade access, model traits, and terminal diagnostics.

---

### 1. `HasFlexFields` Eloquent Trait
Attach dynamic flex fields to any existing Eloquent model (`Product`, `User`, `Order`, etc.) with zero extra database setup required.
- **Fluent Value API**: `$model->setFlexValue('color', '#ff0000')`, `$model->getFlexValue('color')`, `$model->syncFlexValues([...])`.
- **Full Data Retrieval**: `$model->getFlexData()` returns all custom field values as a flat array.
- **Field Definitions**: `$model->getFlexFields()` retrieves active `CustomField` definitions tied to the model's entity.

### 2. `FlexFields` Facade & Fluent Query Engine
Global fluent access for developers to interact with entities and records anywhere in Laravel:
- `FlexFields::entity('product')->records()`: Access records query builder.
- `FlexFields::entity('product')->fields()`: Access active entity custom fields.
- `FlexFields::entity('product')->createRecord([...], [...])`: Create a record and set its field values in a single call.
- `FlexFields::status()`: Retrieve system statistics summary.
- `FlexFields::clearCache()`: Flush cached active fields.

### 3. Active Field Caching (`FieldCache`)
Significantly improves form rendering performance by caching active custom fields per entity:
- **Automatic Invalidation**: Cache is automatically purged whenever fields or entities are created, updated, deleted, or restored.
- **Configurable**: Configurable cache store, prefix, and TTL in `config/flex-fields.php`.

### 4. `DynamicFormBuilder` Macros
Extensible form builder using Laravel's `Macroable` trait:
- Register custom field type component handlers dynamically via `DynamicFormBuilder::macro('my_custom_type', function(CustomField $field) { ... })`.

### 5. `flex:status` Artisan Command
New diagnostic CLI tool to inspect entities, custom fields, records, multi-tenancy, and field cache configuration:
```bash
php artisan flex:status
```

---

**Full Changelog**: https://github.com/ivanmercedes/flex-fields/compare/v0.2.1...0.3.0
