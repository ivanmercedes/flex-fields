# FlexFields — Data Management

Everything needed to move data in and out of FlexFields and to keep track of how it changes.

- [CSV Export](#csv-export)
- [CSV / Excel Import](#csv--excel-import)
- [Field Groups / Sections](#field-groups--sections)
- [Record History / Audit Log](#record-history--audit-log)

All features are implemented once in the domain layer (`src/Services`) and reused by the Filament panel, the REST API, the AI tools and your own code.

---

## CSV Export

No extra dependencies are needed. On every entity's record list you get:

- **Export CSV** (table header): exports every record that matches the current table filters.
- **Export selected to CSV** (bulk action): exports only the selected records.

Columns: `id, title, slug, status, categories, created_at, updated_at` followed by one column per field key.

- Array values (tags, multiselect, repeater, address, translations…) are written as JSON, so the file can be imported back.
- Booleans are written as `1` / `0`.
- Cells starting with `=`, `+`, `-`, `@` are prefixed with `'` to prevent CSV / formula injection in spreadsheet apps. The importer removes that prefix.
- Files start with a UTF-8 BOM so Excel detects the encoding.

From code:

```php
use IvanMercedes\FlexFields\Facades\FlexFields;

$entity = FlexFields::entity('products')->entity();

// Stream a download (controller / action)
return FlexFields::exporter()->download($entity, $entity->records()->where('status', 'published'));

// Or get the CSV as a string
$csv = FlexFields::exporter()->toCsv($entity, $entity->records());
```

---

## CSV / Excel Import

The **Import** button on the record list opens an import page:

1. Download the **template** (header row with `title, slug, status, categories` and every field key).
2. Upload a `.csv` or `.xlsx` file and choose the mode:
   - **Create** — every row becomes a new record.
   - **Upsert** — rows whose `id` or `slug` match an existing record update it; the rest are created.
3. Review the **preview**: each row is validated against the entity schema (types, required fields, select options, relations, your validation rules, categories, status). Nothing is written yet.
4. **Confirm** — valid rows are imported, invalid rows are skipped and reported by line number.

Accepted values:

| Column type | Format |
|---|---|
| `categories` | slugs, names or IDs separated by `|` |
| `boolean` | `1/0`, `true/false`, `yes/no`, `si/no`, `on/off` |
| `tags`, `multiselect` | JSON array or values separated by `|` |
| `relationship` | record ID (or JSON array / `|`-separated IDs when multiple) |
| `json`, `repeater`, `address` | JSON |
| translatable fields | JSON object `{"en": "...", "es": "..."}` or a plain string (stored in the default locale) |

Headers are matched case-insensitively against field keys **or** labels. Unknown columns are listed and ignored.

Configuration (`config/flex-fields.php`):

```php
'import_export' => [
    'disk' => env('FLEX_FIELDS_IMPORT_DISK', 'local'),   // private disk for uploaded files
    'directory' => 'flex-fields-imports',
    'max_rows' => (int) env('FLEX_FIELDS_IMPORT_MAX_ROWS', 5000),
    'csv_delimiter' => ',',
],
```

Uploaded files are stored with a random name on a private disk and deleted after the import (or when it is cancelled).

From code:

```php
$importer = FlexFields::importer();

$rows = $importer->read(storage_path('app/products.xlsx'));
$preview = $importer->preview($entity, $rows, mode: 'upsert'); // validation only
$result = $importer->import($entity, $rows, mode: 'upsert');   // ['created' => 10, 'updated' => 3, 'skipped' => 1, 'errors' => [...]]
```

Each imported record goes through `RecordDomainService`, so it is validated again and gets its own history entry.

---

## Field Groups / Sections

Field groups organize the fields of an entity into labeled, collapsible sections of the record form.

- Manage them from the **Field Groups** tab when editing an entity, or create one on the fly from the *Field Group* select of the custom field form.
- Each group has a label, key, description, order, and *collapsible* / *collapsed by default* options.
- Fields without a group are rendered in the main section, followed by each group in order. Empty groups are not rendered.
- Deleting a group never deletes its fields; they become ungrouped.

Schema builder:

```php
Flex::create('Product', function (Blueprint $table) {
    $table->text('name')->required();

    $table->fieldGroup('seo', 'SEO', 'Search engine settings', collapsed: true, fields: function (Blueprint $table) {
        $table->text('meta_title');
        $table->textarea('meta_description');
    });

    // or assign later
    $table->text('canonical_url')->group('seo');
});
```

From code:

```php
FlexFields::fieldGroups()->create('products', ['label' => 'Pricing', 'fields' => ['price', 'discount']]);
FlexFields::fieldGroups()->update('products', 'pricing', ['is_collapsed' => true]);
FlexFields::fieldGroups()->delete('products', 'pricing');
```

Groups are part of the schema introspection (`groups` and each field's `group`) returned to AI agents and by `GET /api/flex/{entity}/schema`.

---

## Record History / Audit Log

Every change to a record stores a **revision** with:

- the event: `created`, `updated`, `deleted`, `restored` or `reverted`,
- the user who made it (`user_id` + a snapshot of the name),
- a full **snapshot** (title, slug, status, order, meta, categories and every field value),
- a **diff** against the previous revision (`title`, `status`, `categories`, `fields.price`…).

Updates that do not change anything are not recorded.

In the panel, the record edit page has a **History** tab listing the revisions. *View changes* shows the before/after table and *Restore this version* reverts the record (restoring it from the trash if needed). A restore is itself recorded, so it can be undone.

```php
'history' => [
    'enabled' => env('FLEX_FIELDS_HISTORY_ENABLED', true),
    'max_revisions' => (int) env('FLEX_FIELDS_HISTORY_MAX_REVISIONS', 50), // per record, 0 = unlimited
],
```

From code:

```php
$history = FlexFields::history();

$revisions = $history->revisions($record);     // newest first
$revisions->first()->diff;                     // ['fields.price' => ['old' => 10, 'new' => 12]]
$history->revert($revisions->last());          // back to the first version
```

Force-deleting a record removes its revisions.
