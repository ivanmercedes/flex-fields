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
class CreateRecordTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Create a new content record/entry for an entity with its title, status, categories, and custom field values.';
    }

    public function toolCategory(): AiToolCategory
    {
        return AiToolCategory::Content;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity.')
                ->required(),
            'title' => $schema->string()
                ->description('Title of the record.')
                ->required(),
            'slug' => $schema->string()
                ->description('Optional slug for the record. Auto-generated from title if omitted.'),
            'status' => $schema->string()
                ->description('Record publishing status: "published", "draft", "scheduled" (published automatically at publish_at), or "archived". Defaults to "published".')
                ->enum(['published', 'draft', 'scheduled', 'archived']),
            'publish_at' => $schema->string()
                ->description('ISO 8601 date and time to publish the record at. Required when status is "scheduled".'),
            'field_values' => $schema->object()
                ->description('Key-value dictionary mapping custom field keys to their values, e.g. {"price": 29.99, "color": "blue"}. Call GetEntitySchema first to know valid field keys.'),
            'category_ids' => $schema->array()
                ->items($schema->integer())
                ->description('Optional array of category IDs to assign to this record.'),
        ];
    }

    protected function execute(Request $request): ToolResult | string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:published,draft,scheduled,archived'],
            'publish_at' => ['sometimes', 'nullable', 'date'],
            'field_values' => ['sometimes', 'nullable', 'array'],
            'category_ids' => ['sometimes', 'nullable', 'array'],
        ]);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug, FlexPermissions::CREATE)) {
            return $guard;
        }

        // Without the publish permission records default to draft; publishing explicitly is rejected.
        $canPublish = $this->guardEntityAllowed($entitySlug, FlexPermissions::PUBLISH) === null;
        $status = $validated['status'] ?? ($canPublish ? 'published' : 'draft');

        if (in_array($status, ['published', 'scheduled'], true) && ! $canPublish) {
            return $this->guardEntityAllowed($entitySlug, FlexPermissions::PUBLISH);
        }

        try {
            $attributes = [
                'title' => $validated['title'],
                'slug' => $validated['slug'] ?? null,
                'status' => $status,
                'publish_at' => $validated['publish_at'] ?? null,
            ];
            $fieldValues = $validated['field_values'] ?? [];
            $categoryIds = $validated['category_ids'] ?? [];

            $record = $this->recordService()->create($entitySlug, $attributes, $fieldValues, $categoryIds, $this->tenantId());

            return $this->asJson([
                'message' => "Record [{$record->title}] created successfully with ID [{$record->id}].",
                'record' => $this->recordService()->formatRecord($record),
            ]);
        } catch (Throwable $e) {
            return $this->error("Failed to create record for entity [{$entitySlug}]: {$this->describeException($e)}");
        }
    }
}
