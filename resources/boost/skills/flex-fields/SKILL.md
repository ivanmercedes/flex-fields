---
name: FlexFields
description: ACF-like Custom Entities & Custom Fields plugin for Laravel Filament (dynamic entities, field groups, conditional/translatable/relationship fields, CSV import/export, record history, headless REST API, per-entity permissions and Laravel AI SDK tools)
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
  - rest-api
  - laravel-ai
---

# FlexFields Skill for AI Assistants

This project uses the `ivanmercedes/flex-fields` package, which provides Advanced Custom Fields (ACF) style functionality natively for Laravel Filament.

**As an AI agent, use this document to understand how to interact with FlexFields.**

---

## 1. Core Architecture

FlexFields uses an EAV (Entity-Attribute-Value) architecture natively integrated with Filament:
- **`IvanMercedes\FlexFields\Models\Entity`**: A custom data type (like a Post Type in WordPress). e.g., `Product`, `Event`.
- **`IvanMercedes\FlexFields\Models\CustomField`**: A field definition assigned to an Entity (type, key, options, validation rules, settings).
- **`IvanMercedes\FlexFields\Models\FieldGroup`**: A labeled, collapsible form section grouping fields of an Entity.
- **`IvanMercedes\FlexFields\Models\EntityRecord`**: An entry of an Entity. Values live in `FieldValue` (EAV).
- **`IvanMercedes\FlexFields\Models\EntityCategory`**: Hierarchical taxonomies per entity.
- **`IvanMercedes\FlexFields\Models\RecordRevision`**: Audit log entry (event, user, snapshot, diff) of a record.
- **`IvanMercedes\FlexFields\Facades\FlexFields`**: Facade for global fluent entity/record operations.
- **`IvanMercedes\FlexFields\Models\Traits\HasFlexFields`**: Trait to attach flex fields to any Eloquent model.

Tables: `ff_entities`, `ff_custom_fields`, `ff_field_groups`, `ff_entity_records`, `ff_field_values`, `ff_entity_categories`, `ff_entity_record_category`, `ff_record_revisions`.

> **CRITICAL RULES**
> - Do not create standard Filament Resources (`php artisan make:filament-resource`) for data types managed by FlexFields. FlexFields auto-generates the UI (forms and tables) inside its own `EntityDataResource`.
> - Do not create custom migrations/tables to group fields, translate values, link records, audit changes or export/import data: use field groups, translatable fields, `relationship` fields, record history and the importer/exporter.
> - Business logic lives in the domain services (section 8). Prefer them over raw Eloquent writes.

---

## 2. Managing Schemas (Code-First)

FlexFields includes a Schema Builder that behaves like Laravel Migrations. Always use it when the user asks to "create a new entity" or "add fields to an entity" via code.

### Commands
- `php artisan flex:make-schema {EntityName}` (Creates a new schema file in `database/flex-schemas`)
- `php artisan flex:migrate` (Applies pending schemas)
- `php artisan flex:rollback` (Rolls back the last schema batch)
- `php artisan flex:status` (Entity, field, record, tenancy and cache overview)
- `php artisan flex:permissions [--entity=slug] [--sync] [--guard=web]` (Lists or creates the per-entity permissions)
- `php artisan flex:agent` (Interactive FlexFields AI assistant, requires `laravel/ai`)

### Schema Builder Syntax
When generating a schema file inside `database/flex-schemas`, use `IvanMercedes\FlexFields\Schema\Flex` (it is **not** a facade):

```php
use IvanMercedes\FlexFields\Schema\Blueprint;
use IvanMercedes\FlexFields\Schema\Flex;

return new class {
    public function up(): void
    {
        Flex::create('product', function (Blueprint $schema) {
            $schema->description('Manage our product catalog')
                   ->icon('heroicon-o-shopping-bag')
                   ->entityColor('#3b82f6')
                   ->showInMenu(true);

            $schema->image('gallery', 'Product Gallery')
                   ->multiple()
                   ->optimize('webp')
                   ->imageDimensions(1920, null, 80);

            $schema->text('sku', 'SKU')->required()->width('half')->rules(['alpha_dash', 'max:30']);

            $schema->number('price', 'Price')
                   ->required()
                   ->categories([1, 2]) // only visible for records in these category IDs
                   ->width('half');

            $schema->rich('description', 'Description');

            $schema->select('status', 'Product Status')
                   ->options(['active' => 'Active', 'draft' => 'Draft'])
                   ->default('draft');

            // Repeater (nested sub-fields)
            $schema->repeater('features', 'Product Features')
                   ->schema(function (Blueprint $table) {
                       $table->text('feature_title', 'Feature Title')->required();
                       $table->text('feature_icon', 'Icon Name')->width('half');
                   });
        });
    }

    public function down(): void
    {
        Flex::drop('product');
    }
};
```

Field methods: `string`, `text`, `textarea`, `rich`, `number`, `boolean`, `date`, `datetime`, `email`, `url`, `select`, `multiselect`, `tags`, `json`, `image`, `file`, `color`, `repeater`, `phone`, `address`, `relationship($key, $relatedEntitySlug, $label = null, multiple: false)`.

Modifiers: `required()`, `placeholder()`, `description()`, `default()`, `options()`, `rules()`, `width('full'|'half'|'third')`, `searchable()`, `showInList()`, `active()`, `categories()`, `multiple()`, `optimize()`, `imageDimensions()`, `group()`, `showWhen()`, `showWhenAny()`, `translatable()`, `relatedTo()`, `phoneMask()`, `addressParts()`, `settings()`.

---

## 3. `FlexFields` Facade & Fluent Query Engine

```php
use IvanMercedes\FlexFields\Facades\FlexFields;

$entity = FlexFields::entity('product');          // fluent wrapper (EntityQuery)
$fields = $entity->fields();                       // cached active fields
$records = $entity->records()->where('status', 'published')->get();
$record = $entity->findRecord('iphone-15');        // by ID or slug

// Quick write helper (no validation, no history)
$record = $entity->createRecord(['title' => 'iPhone 15 Pro', 'status' => 'published'], ['price' => 999.99]);

$status = FlexFields::status();
FlexFields::clearCache('product');

// Services
FlexFields::exporter();     // RecordExporter
FlexFields::importer();     // RecordImporter
FlexFields::history();      // RecordHistoryService
FlexFields::fieldGroups();  // FieldGroupDomainService
```

> `createRecord()` / `updateRecord()` / `setValue()` write values directly. When the values come from users, an API or an AI agent, use `RecordDomainService` (section 8) so they are validated and audited.

---

## 4. `HasFlexFields` Eloquent Trait

Attach dynamic custom fields directly to any existing Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use IvanMercedes\FlexFields\Models\Traits\HasFlexFields;

class Product extends Model
{
    use HasFlexFields;

    protected string $flexEntitySlug = 'product'; // optional, defaults to kebab-case class name
}

$product->setFlexValue('color', '#ff0000')->setFlexValue('warranty_months', 24);
$color = $product->getFlexValue('color', '#000000');
$product->syncFlexValues(['color' => '#000000', 'in_stock' => true]);
$data = $product->getFlexData();
$fields = $product->getFlexFields();
```

---

## 5. `DynamicFormBuilder` Macros

Register custom field type handlers (the state path must be `ff_{key}`):

```php
use Filament\Forms\Components\TextInput;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Support\DynamicFormBuilder;

DynamicFormBuilder::macro('custom_currency', function (CustomField $field) {
    return TextInput::make('ff_' . $field->key)->label($field->label)->numeric()->prefix('$');
});
```

Add the type to `config('flex-fields.field_types')` so it can be selected in the admin.

---

## 6. Reading Values (EAV Direct Access)

```php
$price = $record->getValue('price');             // cast by field type
$all = $record->data;                            // ['price' => 99.99, 'sku' => '123-ABC']
$record->getTranslation('tagline', 'es');        // translatable field, falls back to the default locale
$record->related('brand');                       // Collection<EntityRecord> of a relationship field
$record->revisions;                              // history, newest first
```

Value format per type (also exposed as `value_format` in the schema introspection):

| Type | PHP / JSON value |
|---|---|
| `number` | float |
| `boolean` | bool |
| `date` / `datetime` | `Y-m-d` / `Y-m-d H:i:s` string |
| `select` | option key |
| `multiselect`, `tags` | array of strings |
| `json`, `repeater` | array (repeater: list of objects with the sub-field keys) |
| `address` | `['street', 'street_2', 'city', 'state', 'postal_code', 'country']` (configured parts only) |
| `relationship` | record ID, or array of IDs when `multiple` |
| `image`, `file` | stored path, or array of paths when `multiple` |
| translatable `text`/`textarea`/`richtext` | `['en' => '...', 'es' => '...']` (locales from `flex-fields.translations.locales`) |

---

## 7. Advanced Field Types & Behavior

```php
Flex::create('product', function (Blueprint $schema) {
    $schema->select('kind')->options(['digital' => 'Digital', 'physical' => 'Physical']);

    // Conditional visibility. Operators: equals, not_equals, in, not_in, contains,
    // filled, blank, gt, gte, lt, lte, is_true, is_false. Chain for AND, add showWhenAny() for OR.
    $schema->number('weight')->showWhen('kind', 'equals', 'physical');

    // Relationship to records of another entity (same tenant)
    $schema->relationship('brand', 'brands');
    $schema->relationship('related', 'products', multiple: true);

    // Phone with mask, structured address, translatable text
    $schema->phone('support_phone')->phoneMask('(999) 999-9999');
    $schema->address('warehouse')->addressParts(['street', 'city', 'country']);
    $schema->text('tagline')->translatable();

    // Field groups (collapsible form sections)
    $schema->fieldGroup('seo', 'SEO', collapsed: true, fields: function (Blueprint $schema) {
        $schema->text('meta_title')->rules(['max:60']);
    });
    $schema->textarea('meta_description')->group('seo');
});
```

Settings stored on `CustomField::$settings`: `show_when` (`{match: all|any, conditions: [{field, operator, value}]}`), `related_entity`, `multiple`, `translatable`, `phone_mask`, `address_parts`, `schema` (repeater).

Validation rules (`validation_rules`) use Laravel rule strings, restricted to a whitelist (`min`, `max`, `between`, `size`, `digits`, `regex`, `starts_with`, `ends_with`, `in`, `not_in`, `date_format`, `after`, `before`, `alpha_num`, `alpha_dash`, `email`, `url`, `integer`, `numeric`, `uuid`, ...). They are applied in Filament forms, the REST API, imports and AI tools. Saving a non-whitelisted rule (`exists:`, `unique:`...) through `FieldDomainService` throws; unknown rules stored by older versions are ignored when validating. Presence is controlled by `is_required`, never by a `required` rule.

---

## 8. Domain Services (single source of business logic)

Filament, the REST API, the importer and the AI tools all go through these services:

- `RecordDomainService` — `list()`, `paginate()`, `find()`, `create()`, `update()`, `setValues()`, `publish()`, `unpublish()`, `delete($force)`, `restore()`, `formatRecord()`. Validates values with `RecordValueValidator` (throws `Illuminate\Validation\ValidationException`), only accepts categories of the entity, wraps writes in transactions and records history.
- `EntityDomainService` — CRUD plus `getSchema()` (fields, groups, categories, `show_when`, relationships, translations, `value_format`).
- `FieldDomainService` — CRUD of fields; validates type, settings (`normalizeSettings()`), group and rules. `update()` merges `settings` (send `null` to remove a key).
- `FieldGroupDomainService` — `create()`, `update()`, `delete()` (fields become ungrouped), `assignFields()`.
- `CategoryDomainService` — category CRUD.
- `RecordHistoryService` — `revisions($record)`, `revert($revision)`, `format()`.
- `RecordExporter` — `download()`, `toCsv()` (JSON for arrays, formula-injection safe).
- `RecordImporter` — `read($path)` (`.csv`/`.xlsx`), `preview($entity, $rows, 'create'|'upsert')`, `import()`.

```php
use IvanMercedes\FlexFields\Services\RecordDomainService;

$record = app(RecordDomainService::class)->create('product', ['title' => 'Chair', 'status' => 'draft'], [
    'price' => 49.9,
    'tagline' => ['en' => 'Comfy', 'es' => 'Cómoda'],
    'brand' => 12,
], categoryIds: ['furniture'], tenantId: $tenantId);
```

All services accept an explicit `$tenantId`; never take the tenant from user input.

---

## 9. Laravel AI SDK Tools

Requires `laravel/ai` (optional dependency). Tools are grouped in categories: `read`, `structure`, `content`, `publishing`, `destructive`.

```php
use IvanMercedes\FlexFields\Ai\FlexFieldsAi;

public function tools(): iterable
{
    return FlexFieldsAi::configure()
        ->forTenant($tenantId)          // tenant comes from the app, never from the model
        ->forUser($user)                // used for per-entity permissions
        ->allowEntities(['products'])   // optional whitelist
        ->withoutCategories(['destructive'])
        ->tools();
}
```

- Destructive tools and `RestoreRecordRevisionTool` implement `Approvable` (human approval) unless `withoutApprovals()` is used.
- Agents should call `GetEntitySchemaTool` first and follow each field's `value_format`.
- Ready-made agent: `FlexFieldsAi::assistant()` / `FlexFieldsAssistant`. Register extra tools with `FlexFieldsAi::registerTool()`.

---

## 10. REST API, Permissions & Media

- **REST API** (opt-in, `FLEX_FIELDS_API_ENABLED=true`): `GET /api/flex/entities`, `GET /api/flex/{entity}/schema`, `GET/POST /api/flex/{entity}`, `GET/PUT/PATCH/DELETE /api/flex/{entity}/{record}`, `POST /api/flex/{entity}/{record}/restore`. Payload: `{title, slug, status, categories: [], values: {key: value}}`; responses expose field values under `values`. Default middleware `['api', 'auth:sanctum']`, token abilities `flex:read`, `flex:write`, `flex:delete`. With tenancy enabled register `IvanMercedes\FlexFields\Http\FlexApi::resolveTenantUsing(fn ($request) => ...)`. Custom transformers via `flex-fields.api.resources`.
- **Permissions** (opt-in, `FLEX_FIELDS_PERMISSIONS_ENABLED=true`): Gate abilities `{ability}_flex_{entity}` (`view_any`, `view`, `create`, `update`, `delete`, `restore`, `force_delete`, `publish`, `import`, `export`) plus `manage_flex_structure`. Check with `IvanMercedes\FlexFields\Authorization\FlexPermissions::allows($user, 'publish', $entity)`. Enforced in Filament, the REST API and AI tools; without `publish`, new records default to `draft`. `php artisan flex:permissions --sync` creates them for spatie/laravel-permission / Filament Shield.
- **Spatie Media Library**: set `flex-fields.models.record` to `IvanMercedes\FlexFields\Models\MediaEntityRecord` and `flex-fields.media.driver` to `spatie` (requires `filament/spatie-laravel-media-library-plugin`).
- **History**: `FLEX_FIELDS_HISTORY_ENABLED`, `FLEX_FIELDS_HISTORY_MAX_REVISIONS` (default 50 per record).

---

## 11. Upgrading / Troubleshooting

- After updating the package always run `php artisan migrate` (see `UPGRADE.md`). Until then FlexFields works without field groups and history (`IvanMercedes\FlexFields\Support\FlexSchema`).
- New built-in field types are available even if the published `config/flex-fields.php` has an older `field_types` list.
- Field caching is on by default: after changing fields outside the services/models run `FlexFields::clearCache('slug')`.
