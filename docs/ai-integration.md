# FlexFields — Laravel AI SDK Integration

FlexFields is **AI-ready**! This integration connects FlexFields with the official **Laravel AI SDK** (`laravel/ai`), allowing conversational and autonomous AI Agents to query, manage, and mutate dynamic entities, custom fields, taxonomies, and content records using first-party Tools.

---

## Architecture Overview

The integration follows a decoupled, layered design that ensures business logic is never duplicated across interfaces:

```text
┌────────────────────────────────────────────────────────┐
│                   Consumer Layers                      │
│   Filament Admin   │   Laravel AI Agent   │   MCP (Future)
└──────────┬─────────┴──────────┬───────────┴───────┬────┘
           │                    │                   │
           │           ┌────────▼────────┐          │
           │           │ FlexFields Tools │         │
           │           └────────┬────────┘          │
           │                    │                   │
           └────────────────────┼───────────────────┘
                                │
                       ┌────────▼────────┐
                       │ Domain Services │
                       │ (Entity/Record) │
                       └────────┬────────┘
                                │
                       ┌────────▼────────┐
                       │ Eloquent Models │
                       │ (EAV / Storage) │
                       └─────────────────┘
```

1. **Domain Services Layer (`IvanMercedes\FlexFields\Services`)**:
   Pure, framework-native PHP services (`EntityDomainService`, `FieldDomainService`, `CategoryDomainService`, `RecordDomainService`). They have **zero** AI SDK dependencies.
2. **AI Adapters Layer (`IvanMercedes\FlexFields\Ai`)**:
   Adapts domain services to Laravel AI SDK `Tool` interfaces, handles schema generation, argument validation, human approval hooks, and context boundaries.
3. **Prepared for MCP**:
   Because all domain operations live in domain services, exposing them via Model Context Protocol (MCP) in the future requires only creating MCP tool adapters calling these exact same services.

---

## Requirements & Optional Dependency

The AI integration is **100% optional**. FlexFields continues to operate normally in environments where Laravel AI SDK is not present.

To enable AI features in your application:

```bash
composer require laravel/ai
```

---

## Quickstart: Adding FlexFields Tools to an Agent

You can add all or specific groups of FlexFields tools to your Laravel AI Agent using the `FlexFieldsAi` factory or the `FlexFields` facade:

```php
namespace App\Ai\Agents;

use IvanMercedes\FlexFields\Ai\FlexFieldsAi;
use Laravel\Ai\Agent;

class ContentManagerAgent extends Agent
{
    /**
     * Define the tools available to this agent.
     */
    public function tools(): array
    {
        return [
            // All FlexFields tools (read, structure, content, publishing, destructive)
            ...FlexFieldsAi::tools(),
        ];
    }

    public function instructions(): string
    {
        return 'You are an intelligent CMS assistant. Inspect entities using GetEntitySchema before creating or updating records.';
    }
}
```

Or via the `FlexFields` facade:

```php
use IvanMercedes\FlexFields\Facades\FlexFields;

public function tools(): array
{
    return [
        ...FlexFields::ai()->tools(),
    ];
}
```

---

## Tool Categories

To adhere to the principle of least privilege, tools are categorized into 5 distinct groups:

| Category | Method | Description |
|---|---|---|
| **Read** | `FlexFieldsAi::readTools()` | Schema introspection, listing entities, viewing records, inspecting field types. Safe and non-mutating. |
| **Structure** | `FlexFieldsAi::structureTools()` | Creating and modifying entities, custom fields, taxonomies, and assigning fields to categories. |
| **Content** | `FlexFieldsAi::contentTools()` | Creating, updating, setting field values, and deleting records. |
| **Publishing** | `FlexFieldsAi::publishingTools()` | Publishing (`status = 'published'`) and unpublishing (`status = 'draft'`) content records. |
| **Destructive** | `FlexFieldsAi::destructiveTools()` | High-impact actions (`DeleteEntityTool`, `DeleteFieldTool`, `DeleteCategoryTool`, `DeleteRecordTool`). |

### Available Tools (23 Native Tools)

| Group | Tools | Description |
|---|---|---|
| **Read** | `ListEntitiesTool`<br>`GetEntitySchemaTool`<br>`ListFieldsTool`<br>`GetFieldTool`<br>`ListCategoriesTool`<br>`ListRecordsTool`<br>`GetRecordTool` | Inspect schema, metadata, field options, records, and taxonomies without mutations. |
| **Structure** | `CreateEntityTool`<br>`UpdateEntityTool`<br>`CreateFieldTool`<br>`UpdateFieldTool`<br>`AssignFieldCategoriesTool`<br>`CreateCategoryTool`<br>`UpdateCategoryTool` | Create and modify entities, custom fields, category hierarchies, and scope fields to specific categories. |
| **Content** | `CreateRecordTool`<br>`UpdateRecordTool`<br>`SetRecordValuesTool` | Manage record entries and their custom field values. |
| **Publishing** | `PublishRecordTool`<br>`UnpublishRecordTool` | Toggle record publishing status (`published` / `draft`). |
| **Destructive** | `DeleteEntityTool`<br>`DeleteFieldTool`<br>`DeleteCategoryTool`<br>`DeleteRecordTool` | Delete entities, fields, categories, or records. Implement `Laravel\Ai\Contracts\Approvable`. |

### Combining Categories

```php
// Only allow reading schemas and creating/updating content (no structural or destructive tools)
$tools = FlexFieldsAi::tools(['read', 'content', 'publishing']);

// Or using the fluent builder:
$tools = FlexFieldsAi::configure()
    ->categories(['read', 'content'])
    ->withoutCategories(['destructive'])
    ->tools();
```

---

## Context & Security Boundaries

The AI agent should **never** arbitrarily determine sensitive execution parameters such as tenant, user ID, or unrestricted access. All tools respect the `AiContext`.

### 1. Multi-Tenancy Scoping

When multi-tenancy is active, tools automatically scope all operations to the current tenant. The agent cannot pass a `tenant_id` argument to manipulate another tenant's data.

```php
$tools = FlexFieldsAi::configure()
    ->forTenant($currentTeam->id)
    ->tools();
```

### 2. Entity Whitelisting

Restrict the AI to only interact with specific entity types:

```php
$tools = FlexFieldsAi::configure()
    ->allowEntities(['blog-posts', 'announcements'])
    ->tools();
```

If the agent attempts to inspect or mutate an entity not in this list, the tool rejects the operation with an explicit error message.

### 3. Read-Only Mode

You can enforce global read-only mode for untrusted or customer-facing agents:

```php
$tools = FlexFieldsAi::configure()
    ->readOnly()
    ->tools();
```

---

## Human Approvals (Destructive Operations)

Destructive tools (`DeleteEntityTool`, `DeleteFieldTool`, `DeleteCategoryTool`, `DeleteRecordTool`) implement `Laravel\Ai\Contracts\Approvable` using `InteractsWithApprovals`.

By default, executing any destructive tool triggers a human approval prompt:

```php
// Prompt triggered: "Permanently delete entity [products] and all of its associated fields, records, and categories. This cannot be undone."
```

### Configuring Approvals

- **Disable approvals globally for background batch agents:**
  ```php
  $tools = FlexFieldsAi::configure()
      ->withoutApprovals()
      ->tools();
  ```

- **Set a custom approval requirement per tool:**
  ```php
  $deleteTool = new DeleteEntityTool;
  $deleteTool->requireApproval('Requires sign-off from the Tech Lead.');
  ```

---

## Schema Introspection (`GetEntitySchemaTool`)

One of the most important capabilities for agents is `GetEntitySchemaTool`. Before an agent generates or updates content, it can discover:
- Field names and unique machine keys
- Data types (`text`, `number`, `select`, `repeater`, `image`, etc.)
- Validation rules and constraints (`required`, `min:3`, etc.)
- Available select / multiselect options
- Nested repeater structures and subfields

**Example Agent Response:**
```json
{
  "name": "Craft Beer",
  "slug": "craft-beer",
  "fields": [
    {
      "key": "abv",
      "label": "Alcohol By Volume",
      "type": "number",
      "is_required": true
    },
    {
      "key": "style",
      "label": "Beer Style",
      "type": "select",
      "options": {"ipa": "IPA", "stout": "Stout", "lager": "Lager"}
    }
  ]
}
```

---

## Configuration Settings

In `config/flex-fields.php`, you can customize the default AI settings:

```php
'ai' => [
    'enabled' => env('FLEX_FIELDS_AI_ENABLED', true),
    'require_approvals' => env('FLEX_FIELDS_AI_REQUIRE_APPROVALS', true),
    'read_only' => env('FLEX_FIELDS_AI_READ_ONLY', false),
    'allowed_entities' => null, // null allows all entities, or ['articles', 'products']
],
```

---

## Registering Custom AI Tools

You can register custom domain tools into the `FlexFieldsAi` catalog:

```php
use IvanMercedes\FlexFields\Ai\FlexFieldsAi;

FlexFieldsAi::registerTool(MyCustomAnalyticsTool::class);
```
