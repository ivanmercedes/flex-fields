# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v0.1.2] - 2026-07-09

### Added
- **Dynamic Repeater Field Type**: Added support for structured sub-fields within a Repeater field using a dedicated schema builder. *(Note: This feature was brought forward from the planned v0.4.0 roadmap).*
- **Laravel Boost Integration**: Added official AI skill documentation (`SKILL.md`) in `resources/boost/skills/flex-fields/` for native auto-discovery by Laravel Boost. This empowers AI assistants with deep context about the package's EAV architecture and Schema Builder.

### Changed
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
