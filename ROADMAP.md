# FlexFields — Roadmap

This document outlines the planned features and improvements for future versions of **ivanmercedes/flex-fields**.

> **Current stable release:** `v0.1.0`
> Community feedback and contributions are always welcome. Feel free to open an issue or discussion on GitHub.

---

## ✅ v0.1.0 — Initial Release *(current)*

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

## 🔵 v0.2.0 — Developer Experience

*Focus: make the package easier to use from PHP code, not just the admin panel.*

- [ ] **`HasFlexFields` Eloquent Trait** — attach flex fields to any existing model (`Product`, `User`, etc.)
- [ ] **`FlexFields` Facade** — fluent global access: `FlexFields::entity('product')->records()`
- [ ] **`DynamicFormBuilder` Macros** — allow third parties to register custom field types without forking
- [ ] **Field caching** — cache active fields per entity with automatic invalidation on save
- [ ] **`flex:status` Artisan command** — show entity/field/record count summary in the terminal
- [ ] **Soft Deletes on `EntityRecord`** — trash bin + restore action in the data resource

---

## 🟡 v0.3.0 — Data Management

*Focus: make data useful beyond the admin panel.*

- [ ] **CSV Export** — export any entity's records to CSV directly from the table (no extra dependencies)
- [ ] **CSV/Excel Import** — bulk import records with downloadable template and preview before confirm
- [ ] **Field Groups / Sections** — group fields into labeled, collapsible sections within a form
- [ ] **Record History / Audit Log** — track who changed what and when; optional version restore
- [ ] **Soft Deletes + Trash UI** — restore or permanently delete archived records

---

## 🟠 v0.4.0 — Advanced Field Types

*Focus: power-user field types that cover complex real-world scenarios.*

- [ ] **`repeater` field type** — structured sub-entries (e.g., product variants with size, color, stock)
- [ ] **`relationship` field type** — link a field to records from another FlexFields entity
- [ ] **`conditional` field visibility** — show/hide fields based on the value of another (`showWhen`)
- [ ] **Visual Validation Builder** — UI for adding `required`, `min`, `max`, `regex` rules without raw JSON
- [ ] **`phone` field type** — dedicated phone number input with masking
- [ ] **`address` field type** — structured address sub-form (street, city, country, zip)

---

## 🔴 v0.5.0 — Headless & Integrations

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
