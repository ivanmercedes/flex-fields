# Upgrade Guide

## Upgrading to 0.6.0 from 0.5.x

0.6.0 completes the Data Management, Advanced Field Types and Headless & Integrations roadmap milestones. Most features are additive or opt-in, but please read this guide before deploying.

### 1. Run the migrations (required)

```bash
composer update ivanmercedes/flex-fields
php artisan migrate
php artisan optimize:clear
```

New tables / columns (additive, no existing data is modified):

- `ff_field_groups` and the nullable `ff_custom_fields.field_group_id` column
- `ff_record_revisions` (record history / audit log)

If you published the package migrations earlier, `2024_01_01_000008_add_is_shown_in_list_to_ff_custom_fields_table` now skips itself when the column already exists.

> Until the migrations run, FlexFields keeps working without field groups and without history, so a deploy that updates the code before migrating does not break the admin panel.

### 2. Configuration (optional)

You do **not** need to republish `config/flex-fields.php`. New sections (`models`, `translations`, `history`, `import_export`, `api`, `permissions`, `media`) fall back to the package defaults, and the new field types (`phone`, `address`, `relationship`) are available even if your published `field_types` list is older.

To customize them, copy the new sections from the package config or republish:

```bash
php artisan vendor:publish --tag="flex-fields-config" --force   # overwrites your file, merge your changes back
```

### 3. Behavior changes to review

| Change | Who is affected | What to do |
|---|---|---|
| **Field validation rules are now applied** in Filament forms, the REST API, imports and AI tools. Previously `validation_rules` were stored but ignored. | Fields with rules defined through the schema builder (`->rules()`) or AI tools. | Review your rules. Only whitelisted rules are applied (`min`, `max`, `between`, `regex`, `alpha_num`, `email`, `url`, `in`, `date_format`, …); unknown or unsafe rules (`exists:`, `unique:`, `string`, typos) are ignored instead of failing. A rule such as `max:10` will now block longer values. |
| **`RecordDomainService` validates record values** (types, select options, required fields, relations) and rejects categories that do not belong to the entity. | AI tools / code using `RecordDomainService` directly. `FlexFields::entity()->createRecord()` and `HasFlexFields` are unchanged. | Make sure required fields are sent when creating records; catch `Illuminate\Validation\ValidationException`. |
| **Record history is enabled by default** (one row per change, max 50 per record). | Everyone. | Disable with `FLEX_FIELDS_HISTORY_ENABLED=false` or tune `FLEX_FIELDS_HISTORY_MAX_REVISIONS`. |
| `FieldDomainService::update()` **merges** `settings` instead of replacing them. | Code updating field settings through the service / AI tools. | Send a key with `null` to remove it. |
| Keys generated from labels handle acronyms: "Canonical URL" → `canonical_url` (was `canonical_u_r_l`). | Only fields created **after** upgrading without an explicit key. Existing keys are never changed. | None. |
| New UI: "History" tab on record edit pages and "Field Groups" tab on entity edit pages. | Everyone. | None. |

### 4. New opt-in features

Disabled until you enable them:

- REST API: `FLEX_FIELDS_API_ENABLED=true` (requires Laravel Sanctum by default; with multi-tenancy register `FlexApi::resolveTenantUsing()`). See [docs/rest-api.md](docs/rest-api.md).
- Per-entity permissions: `FLEX_FIELDS_PERMISSIONS_ENABLED=true`, then `php artisan flex:permissions --sync`. **Enabling it denies everything a user has no permission for**, so sync and assign the permissions first. See [docs/permissions.md](docs/permissions.md).
- Spatie Media Library: `media.driver = spatie` + `models.record = MediaEntityRecord`. See [docs/permissions.md](docs/permissions.md#spatie-media-library).
