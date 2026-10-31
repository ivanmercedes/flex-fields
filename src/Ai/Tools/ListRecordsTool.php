<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\Concerns\InteractsWithFlexDomain;
use IvanMercedes\FlexFields\Ai\ToolResult;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

#[Strict]
class ListRecordsTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'List content records/entries for an entity with optional filters (status, search query, category, field values), sorting and pagination.';
    }

    public function toolCategory(): AiToolCategory
    {
        return AiToolCategory::Read;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity.')
                ->required(),
            'status' => $schema->string()
                ->description('Filter by record status: published, draft, scheduled, or archived.')
                ->enum(['published', 'draft', 'scheduled', 'archived']),
            'search' => $schema->string()
                ->description('Filter records by matching title or slug.'),
            'category_id' => $schema->integer()
                ->description('Filter records assigned to a specific category ID.'),
            'limit' => $schema->integer()
                ->description('Maximum number of records to return (1 to 100, default 25).'),
            'offset' => $schema->integer()
                ->description('Number of records to skip for pagination (default 0).'),
            'filters' => $schema->string()
                ->description('JSON object filtering by field values, e.g. {"price": {"gte": 10}, "kind": "physical", "tags": {"contains": "red"}}. Operators: eq, ne, gt, gte, lt, lte, in, contains, null.'),
            'sort' => $schema->string()
                ->description('Comma separated field keys or columns (title, slug, created_at, updated_at...), "-" prefix for descending, e.g. "-price,title".'),
        ];
    }

    protected function execute(Request $request): ToolResult | string
    {
        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'status' => ['sometimes', 'nullable', 'string', 'in:published,draft,scheduled,archived'],
            'search' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'filters' => ['sometimes', 'nullable', 'json'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $validated['where'] = (array) json_decode((string) ($validated['filters'] ?? '{}'), true);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug, FlexPermissions::VIEW_ANY)) {
            return $guard;
        }

        try {
            $records = $this->recordService()->list($entitySlug, $validated, $this->tenantId());

            $formatted = $records->map(fn ($r) => $this->recordService()->formatRecord($r))->toArray();

            return $this->asJson([
                'entity' => $entitySlug,
                'count' => count($formatted),
                'records' => $formatted,
            ]);
        } catch (Throwable $e) {
            return $this->error("Error listing records for entity [{$entitySlug}]: {$this->describeException($e)}");
        }
    }
}
