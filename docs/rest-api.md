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
| GET | `/api/flex/{entity}` | Paginated records (`status`, `search`, `category`, `per_page`, `page`, `trashed=with\|only`) |
| POST | `/api/flex/{entity}` | Create a record |
| GET | `/api/flex/{entity}/{record}` | Record by ID or slug (`?include=relations`) |
| PUT/PATCH | `/api/flex/{entity}/{record}` | Update a record (partial) |
| DELETE | `/api/flex/{entity}/{record}` | Soft delete (`?force=1` to delete permanently) |
| POST | `/api/flex/{entity}/{record}/restore` | Restore a soft-deleted record |

`{entity}` accepts the slug or ID. Inactive entities and entities outside `allowed_entities` return 404.

### Create / update payload

```json
{
  "title": "iPhone 16",
  "slug": "iphone-16",
  "status": "published",
  "categories": ["phones"],
  "values": {
    "price": 999,
    "brand": 12,
    "name": {"en": "Phone", "es": "Teléfono"}
  }
}
```

`data` is accepted as an alias of `values`. Values are validated against the entity schema; errors return `422` with the usual Laravel `errors` object.

### Response

```json
{
  "data": {
    "id": 1,
    "entity_slug": "products",
    "title": "iPhone 16",
    "slug": "iphone-16",
    "status": "published",
    "categories": [{"id": 1, "name": "Phones", "slug": "phones"}],
    "values": {"price": 999, "brand": 12},
    "created_at": "2026-10-10T10:00:00+00:00",
    "updated_at": "2026-10-10T10:00:00+00:00",
    "deleted_at": null
  }
}
```

Lists include Laravel's `links` and `meta` pagination keys.

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

When [per-entity permissions](permissions.md) are enabled, every endpoint checks them (`view_any`, `view`, `create`, `update`, `delete`, `force_delete`, `restore`). Creating or changing the status to/from `published` also requires `publish`; without it new records default to `draft`.

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
