# FlexFields — Headless REST API

FlexFields can expose your entities as a JSON API for frontends and mobile apps. The API is **disabled by default**.

## Enable

```env
FLEX_FIELDS_API_ENABLED=true
```

```php
// config/flex-fields.php
'api' => [
    'enabled' => env('FLEX_FIELDS_API_ENABLED', false),
    'prefix' => env('FLEX_FIELDS_API_PREFIX', 'api/flex'),
    'middleware' => ['api', 'auth:sanctum'],
    'allowed_entities' => null,   // or ['products', 'posts']
    'per_page' => 25,
    'max_per_page' => 100,
    'token_abilities' => [
        'read' => 'flex:read',
        'write' => 'flex:write',
        'delete' => 'flex:delete',
    ],
    'tenant_resolver' => null,
    'resources' => [
        'record' => \IvanMercedes\FlexFields\Http\Resources\RecordResource::class,
        'entity' => \IvanMercedes\FlexFields\Http\Resources\EntitySchemaResource::class,
    ],
],
```

## Endpoints

| Method | URI | Description |
|---|---|---|
| GET | `/api/flex/entities` | Active entities the caller can read |
| GET | `/api/flex/{entity}/schema` | Fields, groups, categories, locales |
| GET | `/api/flex/{entity}` | Paginated records (`status`, `search`, `category`, `filter`, `sort`, `per_page`, `page`, `trashed=with\|only`) |
| POST | `/api/flex/{entity}` | Create a record |
| GET | `/api/flex/{entity}/{record}` | Record by ID or slug |
| GET | `/api/flex/_preview/{token}` | Record of a [preview token](#draft-previews), whatever its status |
| PUT/PATCH | `/api/flex/{entity}/{record}` | Update a record (partial) |
| DELETE | `/api/flex/{entity}/{record}` | Soft delete (`?force=1` to delete permanently) |
| POST | `/api/flex/{entity}/{record}/restore` | Restore a soft-deleted record |

`{entity}` accepts the slug or ID. Inactive entities and entities outside `allowed_entities` return 404.

### Filtering and sorting by field values

```
GET /api/flex/products?filter[price][gte]=10&filter[kind]=physical&filter[tags][contains]=sale&sort=-price,title
```

- `filter[field]=value` is an equality check; `filter[field][operator]=value` uses `eq`, `ne`, `gt`, `gte`, `lt`, `lte`, `in` (comma separated), `contains` or `null` (`true` / `false`).
- Numbers are compared numerically and dates as `Y-m-d`. `tags` / `multiselect` match by element and `relationship` by related record ID. Booleans without a value count as `false`.
- Translatable fields support `contains` and `null`; rich text, JSON, repeater, address, link and media fields only `null`.
- `sort` takes field keys or `id`, `title`, `slug`, `order`, `status`, `publish_at`, `created_at`, `updated_at`; prefix with `-` for descending.

Unknown fields or unsupported operators return `422`. The same filters are available in PHP (`RecordDomainService::query($entity, ['where' => [...], 'sort' => '-price'])`) and in the `ListRecords` AI tool.

### Presentation options (lists and single records)

| Query | Effect |
|---|---|
| `?locale=es` | Translatable values resolved to that locale, falling back to `translations.fallback_locale` |
| `?fields=price,cover` | Only these field keys in `values` and `media` |
| `?include=relations` | Expand relationship fields (`id`, `title`, `slug`, `status`) |
| `?include=relations.values` | ...including the related records' `values` and `media` |

### Create / update payload

```json
{
  "title": "iPhone 16",
  "slug": "iphone-16",
  "status": "published",
  "publish_at": null,
  "categories": ["phones"],
  "values": {
    "price": 999,
    "brand": 12,
    "name": {"en": "Phone", "es": "Teléfono"}
  }
}
```

`data` is accepted as an alias of `values`. Values are validated against the entity schema; errors return `422` with the usual Laravel `errors` object.

`status` is `draft`, `published`, `archived` or `scheduled`. A `scheduled` record needs a `publish_at` date and is published by the `flex:publish-scheduled` command (added to the scheduler every minute, see `scheduling.enabled`) once that date passes; a date in the past publishes it right away.

### Response

```json
{
  "data": {
    "id": 1,
    "entity_slug": "products",
    "title": "iPhone 16",
    "slug": "iphone-16",
    "status": "published",
    "publish_at": null,
    "categories": [{"id": 1, "name": "Phones", "slug": "phones"}],
    "values": {"price": 999, "brand": 12, "cover": "flex-fields/acme/products/cover/iphone.jpg"},
    "media": {"cover": "https://cdn.example.com/flex-fields/acme/products/cover/iphone.jpg"},
    "created_at": "2026-10-10T10:00:00+00:00",
    "updated_at": "2026-10-10T10:00:00+00:00",
    "deleted_at": null
  }
}
```

Lists include Laravel's `links` and `meta` pagination keys.

`values` keep the stored file paths of `image` / `file` fields (send them back when writing); `media` mirrors them with absolute URLs on the uploads disk, including translatable fields (per locale) and the media sub-fields of repeaters. It is an empty object when the record has no files.

## Authentication with Sanctum

The default middleware is `auth:sanctum`. Install Sanctum in your application (`php artisan install:api`) and issue tokens with the FlexFields abilities:

```php
$token = $user->createToken('mobile-app', ['flex:read', 'flex:write'])->plainTextToken;
```

- `flex:read` — GET endpoints
- `flex:write` — POST / PUT / PATCH / restore
- `flex:delete` — DELETE

Abilities are only checked for personal access tokens; first-party SPA (cookie) authentication is not restricted. Change the middleware to use any other guard, or to expose a public read-only API (e.g. `['api']` plus your own middleware).

## Multi-tenancy

The tenant is **never** taken from the request payload. When tenancy is enabled you must tell FlexFields how to resolve it, otherwise the API answers `403`:

```php
use IvanMercedes\FlexFields\Http\FlexApi;

// AppServiceProvider::boot()
FlexApi::resolveTenantUsing(fn (Request $request) => $request->user()?->current_team_id);
```

or set `'tenant_resolver' => App\Support\ResolveFlexTenant::class` (an invokable class receiving the request).

## Permissions

When [per-entity permissions](permissions.md) are enabled, every endpoint checks them (`view_any`, `view`, `create`, `update`, `delete`, `force_delete`, `restore`). Creating or changing the status to/from `published` or `scheduled`, or rescheduling, also requires `publish`; without it new records default to `draft`.

## Draft previews

`RecordPreview::token($record)` returns a signed, URL safe token that expires after `preview.ttl` minutes. A website exchanges it for the record (whatever its status) at `GET /api/flex/_preview/{token}`, with the same authentication, tenant and `view` permission checks as the other endpoints, so it never has to trust a plain `?preview=1&id=5` parameter.

Set `preview.url` (`{token}`, `{entity}`, `{slug}` and `{id}` placeholders) or register a resolver to show a **Preview** button on the record edit page:

```php
use IvanMercedes\FlexFields\Support\RecordPreview;

RecordPreview::urlUsing(fn (EntityRecord $record, string $token) => "https://{$record->tenant->domain}/preview?token={$token}");
```

## Extending the record representation

Add keys to every record without replacing the resource:

```php
use IvanMercedes\FlexFields\Http\Resources\RecordResource;

RecordResource::extend(fn (array $data, EntityRecord $record, Request $request) => $data + [
    'url' => route('products.show', $record->slug),
]);
```

## Custom transformers

Extend the default resources and register them in `api.resources`:

```php
class ProductResource extends \IvanMercedes\FlexFields\Http\Resources\RecordResource
{
    public function toArray($request): array
    {
        return [...parent::toArray($request), 'url' => route('products.show', $this->resource->slug)];
    }
}
```

## Architecture

Controllers only adapt HTTP to the domain: every operation is executed by `RecordDomainService` / `EntityDomainService`, the same services used by Filament and the AI tools, so validation, history, tenancy and permissions behave identically.
