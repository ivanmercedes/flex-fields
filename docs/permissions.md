# FlexFields — Permissions & Integrations

- [Permissions / Policies per Entity](#permissions--policies-per-entity)
- [Filament Shield](#filament-shield)
- [Spatie Media Library](#spatie-media-library)

---

## Permissions / Policies per Entity

Disabled by default. When enabled, every entity gets its own set of permissions and structural screens require a dedicated permission.

```env
FLEX_FIELDS_PERMISSIONS_ENABLED=true
```

```php
'permissions' => [
    'enabled' => env('FLEX_FIELDS_PERMISSIONS_ENABLED', false),
    'format' => '{ability}_flex_{entity}',
    'abilities' => ['view_any', 'view', 'create', 'update', 'delete', 'restore', 'force_delete', 'publish', 'import', 'export'],
    'structure_permission' => 'manage_flex_structure',
],
```

For an entity with slug `blog-posts` the permissions are `view_any_flex_blog_posts`, `create_flex_blog_posts`, `publish_flex_blog_posts`, … plus the global `manage_flex_structure`.

Permissions are checked through Laravel's **Gate**, so they work with:

- `spatie/laravel-permission` / Filament Shield (their `Gate::before` resolves permissions),
- your own `Gate::define()` / `Gate::before()` logic.

Where they apply:

| Area | Check |
|---|---|
| Record resource (list, create, edit, delete, restore, force delete, bulk actions) | the matching ability of the current entity, on top of your policies |
| Navigation items of each entity | `view_any` |
| Export / Import actions | `export` / `import` |
| Restore a history revision | `update` |
| Entities, custom fields, categories resources | `manage_flex_structure` |
| AI tools | read tools `view_any`/`view`, content tools `create`/`update`/`delete`, publishing tools `publish`, structure tools `manage_flex_structure` |
| REST API | same abilities per endpoint |

Records created without the `publish` permission default to `draft` (API and AI tools).

```php
use IvanMercedes\FlexFields\Authorization\FlexPermissions;

FlexPermissions::allows($user, FlexPermissions::PUBLISH, $entity);
FlexPermissions::permissionName('create', 'products'); // create_flex_products
FlexPermissions::all();                                   // every permission name
```

## Filament Shield

List or create the permissions:

```bash
php artisan flex:permissions                  # list
php artisan flex:permissions --entity=products
php artisan flex:permissions --sync           # create them with spatie/laravel-permission
php artisan flex:permissions --sync --guard=web
```

After syncing, assign them to roles from Shield's role editor (custom permissions). Run the command again after creating new entities.

---

## Spatie Media Library

`image` and `file` fields store file paths by default. To use [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary) instead:

```bash
composer require filament/spatie-laravel-media-library-plugin
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

```php
// config/flex-fields.php
'models' => [
    'record' => \IvanMercedes\FlexFields\Models\MediaEntityRecord::class,
],
'media' => [
    'driver' => 'spatie',
    'collection_prefix' => 'ff_',
],
```

- Upload fields use `SpatieMediaLibraryFileUpload`; each field has its own collection (`ff_{field_key}`).
- `RecordDomainService::formatRecord()` (AI tools, REST API) returns the media URLs for those fields.
- Conversions, responsive images and custom disks are configured as usual in Spatie Media Library (you can extend `MediaEntityRecord` and register it in `models.record`).

If the driver is `spatie` but the packages are missing, FlexFields silently falls back to native uploads.
