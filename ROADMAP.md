# FlexFields — Roadmap

This document outlines the planned features and improvements for future versions of **ivanmercedes/flex-fields**.

> **Current stable release:** `0.3.1`
> Community feedback and contributions are always welcome. Feel free to open an issue or discussion on GitHub.

---

## ✅ 0.3.1 — Developer Experience & Boost Skill Update *(current)*


*Focus: make the package easier to use from PHP code, not just the admin panel.*

- [x] **`HasFlexFields` Eloquent Trait** — attach dynamic flex fields to any existing Eloquent model (`Product`, `User`, etc.)
- [x] **`FlexFields` Facade** — fluent global access: `FlexFields::entity('product')->records()`, `createRecord()`, `status()`
- [x] **`DynamicFormBuilder` Macros** — allow third parties to register custom field type components dynamically (`DynamicFormBuilder::macro(...)`)
- [x] **Field Caching** — cache active fields per entity (`FieldCache`) with automatic cache invalidation on model save/delete/restore
- [x] **`flex:status` Artisan command** — show entity/field/record count and configuration summary in terminal

---

## ✅ 0.2.1 — Media, Uploads & Timestamps

- [x] **Image Optimization & Conversion** — built-in multi-engine optimizer (`ImageOptimizer`) supporting WebP, JPG, PNG, AVIF with target dimensions and compression quality
- [x] **Dynamic Upload Directory Patterns** — configurable upload paths supporting placeholders (`{tenant_slug}`, `{entity_slug}`, `{field_key}`, `{year}`, `{month}`)
- [x] **Storage Disk & Visibility** — configurable storage disk (`FLEX_FIELDS_DISK`) and file visibility (`FLEX_FIELDS_VISIBILITY`, `'public'` or `'private'`)
- [x] **Multiple Image Upload Grid** — thumbnail grid panel layout for multi-image fields
- [x] **Record Timestamps Editing** — top-level `created_at` and `updated_at` datetime pickers in `EntityDataResource`

---

## ✅ 0.1.3 — Security & CI Improvements

- [x] **Security Policy** — Added `SECURITY.md`
- [x] **Dependabot** — Configured automated dependency updates
- [x] **GitHub Actions** — Pinned actions to secure SHAs

---

## ✅ 0.1.2 — Soft Deletes & Multi-Tenancy Update

- [x] **Soft Deletes on `EntityRecord`** — trash bin + restore and force delete actions in data resource
- [x] **Filament Multi-Tenancy Support** — tenant scoping for entities, fields, categories, records, and navigation
- [x] **Dynamic Repeater Field Type** — fluent nested schema builder API for repeater fields (`->schema(...)`)
- [x] **Laravel Boost Integration** — AI skill documentation (`SKILL.md`) for native auto-discovery by Laravel Boost
- [x] **Database Optimizations** — `jsonb` column migration and compound index performance tuning

---

## ✅ 0.1.0 — Initial Release
The foundation. Everything needed to get started with dynamic entities and custom fields inside Filament.

- Custom Entities (like post types)
- 16+ field types: text, textarea, number, email, URL, date, datetime, boolean, select, multiselect, color, file, image, rich text, JSON, tags
- Entity-Attribute-Value (EAV) storage
- Drag-and-drop field reordering
- Hierarchical Entity Categories per entity
- Dynamic form & table generation
- Schema Builder — code-first entity definitions (`Flex::create`, `Flex::update`, `Flex::drop`)
- Artisan commands: `flex:make-schema`, `flex:migrate`, `flex:rollback`, `flex-fields:install`
- Built-in Filament Dashboard page and overview widget
- Field layout control: full / half / one-third width
- Field settings: required, searchable, shown in list, active
- Internationalization support (lang files)
- Plugin options: `showDashboardPage()`, `showOverviewWidget()`

---

## 🟡 0.4.0 — Data Management

*Focus: make data useful beyond the admin panel.*

- [ ] **CSV Export** — export any entity's records to CSV directly from the table (no extra dependencies)
- [ ] **CSV/Excel Import** — bulk import records with downloadable template and preview before confirm
- [ ] **Field Groups / Sections** — group fields into labeled, collapsible sections within a form
- [ ] **Record History / Audit Log** — track who changed what and when; optional version restore

---

## 🟠 0.5.0 — Advanced Field Types

*Focus: power-user field types that cover complex real-world scenarios.*

- [ ] **`relationship` field type** — link a field to records from another FlexFields entity
- [ ] **`conditional` field visibility** — show/hide fields based on the value of another (`showWhen`)
- [ ] **Visual Validation Builder** — UI for adding `required`, `min`, `max`, `regex` rules without raw JSON
- [ ] **`phone` field type** — dedicated phone number input with masking
- [ ] **`address` field type** — structured address sub-form (street, city, country, zip)

---

## 🔴 0.6.0 — Headless & Integrations

*Focus: use FlexFields outside of Filament.*

- [ ] **Auto REST API** — optional `GET/POST/PUT/DELETE /api/flex/{entity}` endpoints per entity
- [ ] **Sanctum authentication** for the API layer
- [ ] **API Resource transformers** — clean JSON output for frontend/mobile consumers
- [ ] **Translatable Fields** — mark any field as translatable (stores `{"es":"Hola","en":"Hello"}`)
- [ ] **Spatie Media Library integration** — use Spatie's media library for `image` and `file` fields
- [ ] **Permissions / Policies per Entity** — fine-grained access control integrated with Filament Shield

---

## 💡 Ideas Under Consideration

These are not yet scheduled but may be included in a future version based on community demand:

- **Webhooks** — fire a webhook when a record is created/updated/deleted
- **GraphQL support** — expose entities via a GraphQL schema
- **Field Templates** — save a set of fields as a reusable template to apply to multiple entities
- **Import/Export Entity Schemas** — export an entity definition as JSON and import it elsewhere
- **Multi-panel support** — register FlexFields in more than one Filament panel simultaneously

---

## Contributing

If you'd like to see a feature sooner, or want to contribute an implementation, please open an issue on [GitHub](https://github.com/ivanmercedes/flex-fields) describing the use case. PRs are welcome!
