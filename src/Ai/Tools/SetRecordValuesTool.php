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
class SetRecordValuesTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Set or update custom field values on an existing record without changing title or other attributes.';
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
                ->description('The record ID or unique slug.')
                ->required(),
            'field_values' => $schema->object()
                ->description('Key-value dictionary mapping custom field keys to their new values, e.g. {"price": 49.99, "is_featured": true}.')
                ->required(),
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
            'field_values' => ['required', 'array'],
        ]);

        $entitySlug = $validated['entity'];
        $recordIdOrSlug = $validated['record'];
        $fieldValues = $validated['field_values'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $record = $this->recordService()->setValues($entitySlug, $recordIdOrSlug, $fieldValues, $this->tenantId());

            return $this->asJson([
                'message' => "Field values updated for record [{$record->title}] (ID: {$record->id}).",
                'updated_fields' => array_keys($fieldValues),
                'record_data' => $record->data,
            ]);
        } catch (Throwable $e) {
            return "Failed to set values for record [{$recordIdOrSlug}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
