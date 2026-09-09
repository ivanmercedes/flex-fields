<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\Concerns\InteractsWithFlexDomain;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

#[Strict]
class UpdateRecordTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Update an existing content record\'s title, slug, status, categories, or custom field values.';
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
            'record' => $schema->string()
                ->description('The record ID or unique slug to update.')
                ->required(),
            'title' => $schema->string()
                ->description('New title for the record.'),
            'slug' => $schema->string()
                ->description('New slug for the record.'),
            'status' => $schema->string()
                ->description('New status: published, draft, or archived.')
                ->enum(['published', 'draft', 'archived']),
            'field_values' => $schema->object()
                ->description('Key-value dictionary mapping custom field keys to updated values.'),
            'category_ids' => $schema->array()
                ->items($schema->integer())
                ->description('Array of category IDs to assign to this record.'),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'record' => ['required', 'string'],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:published,draft,archived'],
            'field_values' => ['sometimes', 'nullable', 'array'],
            'category_ids' => ['sometimes', 'nullable', 'array'],
        ]);

        $entitySlug = $validated['entity'];
        $recordIdOrSlug = $validated['record'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $attributes = array_intersect_key($validated, array_flip(['title', 'slug', 'status']));
            $fieldValues = $validated['field_values'] ?? [];
            $categoryIds = $validated['category_ids'] ?? null;

            $record = $this->recordService()->update(
                $entitySlug,
                $recordIdOrSlug,
                $attributes,
                $fieldValues,
                $categoryIds,
                $this->tenantId()
            );

            return $this->asJson([
                'message' => "Record [{$record->title}] (ID: {$record->id}) updated successfully.",
                'record' => $this->recordService()->formatRecord($record),
            ]);
        } catch (Throwable $e) {
            return "Failed to update record [{$recordIdOrSlug}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
