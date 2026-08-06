# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v0.2.1] - 2026-08-06

### Added
- **Image Optimization & Conversion**: Added built-in image optimization for custom image fields with configurable format (WebP, JPG, PNG, AVIF), target dimensions (`image_max_width`, `image_max_height`), and compression quality. Includes a multi-engine optimizer (`ImageOptimizer`) supporting Intervention Image v3/v4, Imagick, and native GD with a candidate selection algorithm that guarantees file size reduction.
- **Dynamic Upload Directory Patterns**: Added configurable upload paths via `config/flex-fields.php` and `.env` (`FLEX_FIELDS_DIRECTORY_PATTERN`) supporting tenant (`{tenant_slug}`, `{tenant_id}`), entity (`{entity_slug}`), field (`{field_key}`), and date (`{year}`, `{month}`) placeholders.
- **Storage Disk & Visibility**: Added configurable storage disk (`FLEX_FIELDS_DISK`) and file visibility (`FLEX_FIELDS_VISIBILITY`, `'public'` or `'private'`) for file and image uploads.
- **Multiple Image Upload Grid**: Added support for multi-image upload fields with grid thumbnail layout (`panelLayout('grid')` / `grid(3)`).
- **Record Timestamps Editing**: Added top-level `created_at` and `updated_at` datetime fields to `EntityDataResource` placed directly below status and categories.

### Fixed
- **Image Resize Exception Guard**: Fixed Filament `FileUpload` argument type exceptions for `imageResizeTargetWidth` and `imageResizeTargetHeight` by casting dimension values to strings.

## [v0.1.3] - 2026-07-11

### Added
- **Security Policy**: Added a `SECURITY.md` file detailing supported versions and vulnerability reporting procedures.
- **Dependabot**: Added Dependabot configuration to automate updates for composer packages and GitHub Actions.

### Changed
- **GitHub Actions**: Pinned all GitHub Actions to specific commit SHAs to improve CI/CD pipeline security.

## [v0.1.2] - 2026-07-10

### Added
- **Soft Deletes**: Implemented Soft Deletes for the `EntityRecord` model. The `EntityDataResource` now includes a `TrashedFilter`, and provides `RestoreAction` and `ForceDeleteAction` on individual records and in bulk actions to manage the recycle bin directly from the interface.
- **Filament Multi-Tenancy Support**: Architected and implemented support for Filament's multi-tenancy system. Models and Resources can now be scoped to a tenant via a configurable `tenant_id` column, managed via `config/flex-fields.php`.
- **Dynamic Repeater Field Type**: Added support for structured sub-fields within a Repeater field. Includes a fluent `schema()` API on `FieldDefinition` to define nested sub-fields gracefully using closures (e.g., `->schema(function (Blueprint $table) { ... })`). *(Note: This feature was brought forward from the planned v0.4.0 roadmap).*
- **Laravel Boost Integration**: Added official AI skill documentation (`SKILL.md`) in `resources/boost/skills/flex-fields/` for native auto-discovery by Laravel Boost. This empowers AI assistants with deep context about the package's EAV architecture and Schema Builder.
- **Plugin Options**: Added `showEntitiesInMenu(bool)` method to `FlexFieldsPlugin` to globally disable/enable rendering entities in the sidebar navigation and automatically hide related UI options in `EntityResource`.

### Fixed
- **Database Migrations**: Resolved a rollback error when dropping the `deleted_at` column by explicitly dropping its index first.
- **URL Filtering**: Resolved an issue where deep links to the Custom Fields page were not correctly applying the entity filter due to Filament's nested query parameter parsing. Switched to using a simple `?entity=ID` query parameter to pre-fill the table filter securely.
- **Tenant Isolation**: Fixed an issue where all entities across all tenants were shown in the sidebar menu and could be accessed via URL manipulation. Navigation items and dashboard metrics are now strictly scoped to the active tenant.

### Changed
- **Database Optimizations**: Migrated all `json` columns to `jsonb` for significant performance improvements on supported databases (like PostgreSQL) without breaking MySQL/SQLite compatibility. Also added a missing index on the `deleted_at` column and a unique compound index for `[tenant_id, entity_id, slug]` on `ff_entity_records` to optimize frontend lookups.
- **UI Adjustments**: Updated the layout of `CustomFieldResource` and `EntityResource` to stack form sections vertically in a single column, significantly improving layout readability.

## [v0.1.1] - 2026-07-09

### Changed
- **Dependencies**: Updated PHP requirement to 8.3 and added support for Filament 4.x/5.x.

### Removed
- **Docs**: Removed custom field type mapping documentation from README.

## [v0.1.0] - 2026-07-09

### Added
- **Core**: Initial release of FlexFields!
- **Entities**: Custom entities (like post types) with categories.
- **Fields**: 16+ field types (text, textarea, number, email, URL, date, datetime, boolean, select, multiselect, color, file, image, rich text, JSON, tags).
- **Schema Builder**: Code-first entity definitions and migration system (`make-schema`, `migrate`, `rollback`).
- **Filament Integration**: Built-in Dashboard page, overview widget, and dynamic form/table generation.
- **Slugs**: Automated unique slug generation for entities, records, and categories.
- **Documentation**: Comprehensive roadmap and facade usage documentation.

### Changed
- Grouped entity category form fields into sections with descriptive labels and placeholders.
- Updated entity filter key in FlexFieldsDashboard.

### Removed
- Removed `order` field from `CustomFieldResource` form schema.
- Removed `DynamicTableBuilder` and the `is_shown_in_list` configuration property.
