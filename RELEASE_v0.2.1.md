# Release v0.2.1 - Image Optimization, Dynamic Upload Paths, Multi-Image Grid & Timestamps

## What's New in v0.2.1

We are excited to announce v0.2.1 of `ivanmercedes/flex-fields`! This release introduces smart image optimization, dynamic upload path patterns, multi-image upload grid layouts, and enhanced timestamp controls for records.

---

### Image Optimization & Conversion
- **Smart Candidate Engine**: Built-in `ImageOptimizer` supporting Intervention Image v3/v4, Imagick, and native PHP GD.
- **Guaranteed Size Reduction**: Automatically tests candidate formats (WebP, JPG, PNG) and dimensions (`image_max_width`, `image_max_height`), selecting the smallest file size without risking file size expansion.
- **Configurable Formats & Quality**: Custom fields can define target formats (`webp`, `jpg`, `png`, `avif`), max dimensions, and compression quality factor.

### Dynamic Upload Paths & Storage Settings
- **Customizable Directory Patterns**: Set dynamic patterns via `FLEX_FIELDS_DIRECTORY_PATTERN` in `.env` or `config/flex-fields.php`.
- **Placeholder Support**: Replaces `{tenant_slug}`, `{tenant_id}`, `{entity_slug}`, `{field_key}`, `{field_label}`, `{year}`, and `{month}`. Automatically sanitizes empty segments if no tenant is logged in.
- **Disk & Visibility Control**: Configure upload storage disk (`FLEX_FIELDS_DISK`) and file visibility (`FLEX_FIELDS_VISIBILITY`, `'public'` or `'private'`).

### Multi-Image Upload Grid Layout
- Multiple image upload custom fields now display uploaded thumbnail previews in a clean grid panel layout.

### Record Timestamps Management
- Top-level `created_at` and `updated_at` datetime picker fields added to `EntityDataResource` directly below status and category selections.

---

### Fixes & Adjustments
- Fixed Filament `FileUpload` argument type exceptions for `imageResizeTargetWidth` and `imageResizeTargetHeight` by casting numeric inputs to strings.

---

**Full Changelog**: https://github.com/ivanmercedes/flex-fields/compare/v0.1.3...v0.2.1
