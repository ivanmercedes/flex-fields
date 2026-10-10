# FlexFields — Advanced Field Types

Besides the basic types (text, textarea, number, email, URL, date, datetime, boolean, select, multiselect, color, file, image, rich text, JSON, tags and repeater), FlexFields ships with:

- [`relationship`](#relationship) — link to records of another entity
- [Conditional visibility (`showWhen`)](#conditional-visibility-showwhen)
- [Visual Validation Builder](#visual-validation-builder)
- [`phone`](#phone)
- [`address`](#address)
- [Translatable fields](#translatable-fields)

Values are validated with the same rules everywhere: Filament forms, the REST API, CSV imports and AI tools (`RecordValueValidator`).

---

## `relationship`

Links a field to one or many records of another FlexFields entity.

| Setting | Description |
|---|---|
| `related_entity` | Slug (or ID) of the related entity. Required. |
| `multiple` | Allow selecting several records. |

- The form renders a searchable select with the records of the related entity.
- The value is stored as a record ID (or an array of IDs when `multiple`).
- IDs are validated: they must exist in the related entity **of the same tenant**.

```php
$table->relationship('brand', 'brands');                    // single
$table->relationship('related_products', 'products', multiple: true);
```

```php
$record->getValue('brand');      // 12
$record->related('brand');       // Collection of EntityRecord
```

The REST API expands relations with `?include=relations`.

---

## Conditional visibility (`showWhen`)

Show a field only when other fields match some conditions. Stored in the field settings:

```php
'show_when' => [
    'match' => 'all',  // all | any
    'conditions' => [
        ['field' => 'kind', 'operator' => 'equals', 'value' => 'physical'],
    ],
],
```

Operators: `equals`, `not_equals`, `in`, `not_in` (comma-separated list or array), `contains`, `filled`, `blank`, `gt`, `gte`, `lt`, `lte`, `is_true`, `is_false`.

- Configure it in the **Conditional Visibility** section of the custom field form.
- Controlling fields are made *live* automatically, so the form reacts instantly.
- Hidden fields are not validated (a required field is only required when visible).
- It combines with category-based visibility (`category_ids`).
- References to unknown fields or to the field itself are rejected.

Schema builder:

```php
$table->number('weight')->showWhen('kind', 'equals', 'physical');
$table->url('download_url')->showWhen('kind', 'equals', 'digital')->showWhen('is_free', 'is_true')->showWhenAny();
```

The evaluator (`IvanMercedes\FlexFields\Support\ConditionEvaluator`) is plain PHP, so headless frontends can apply the same rules from the schema returned by the API.

---

## Visual Validation Builder

The **Validation Rules** section of the custom field form lets you add rules without writing raw JSON: pick a rule (min, max, between, regex, starts_with, alpha_num, email, url, date_format, after, before, in, …) and its value.

Rules are stored as standard Laravel rule strings (`['min:3', 'regex:/^[A-Z]/']`), so they stay compatible with the schema builder:

```php
$table->text('sku')->rules(['min:3', 'alpha_dash']);
```

Rules are applied in Filament forms and in the domain validator. Only whitelisted rules are accepted from untrusted sources (AI agents, API): rules such as `exists:` or `unique:` that could probe the database are rejected, and regular expressions are checked before saving.

---

## `phone`

A telephone input (`type="tel"`) with optional input mask.

| Setting | Description |
|---|---|
| `phone_mask` | Alpine mask, `9` = digit, e.g. `(999) 999-9999` |

Values may contain digits, spaces, `+`, `-`, `(`, `)`, `.`, `#`, `*` and `x` (max 50 chars).

```php
$table->phone('mobile')->phoneMask('(999) 999-9999');
```

---

## `address`

A structured sub-form stored as a JSON object with the parts `street`, `street_2`, `city`, `state`, `postal_code`, `country`.

| Setting | Description |
|---|---|
| `address_parts` | Subset of parts to show (default: all) |

When the field is required, `street`, `city` and `country` are required.

```php
$table->address('shipping_address')->addressParts(['street', 'city', 'postal_code', 'country']);

$record->getValue('shipping_address'); // ['street' => 'Main St 1', 'city' => 'Santo Domingo', ...]
```

---

## Translatable fields

`text`, `textarea` and `richtext` fields can be marked as **translatable**. The form shows one tab per locale and the value is stored as:

```json
{"en": "Hello", "es": "Hola"}
```

```php
// config/flex-fields.php
'translations' => [
    'locales' => ['en', 'es'],
    'fallback_locale' => null, // null = first locale
],
```

```php
$table->text('title')->translatable();

$record->getValue('title');                 // ['en' => 'Hello', 'es' => 'Hola']
$record->getTranslation('title', 'es');     // 'Hola'
$record->getTranslation('title', 'fr');     // 'Hello' (fallback)
```

When a field is required, the fallback locale is required. Values saved before a field became translatable are read as the fallback locale.
