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
class GetRecordTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Get a specific content record by ID or slug within an entity, including all its casted custom field values.';
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
            'record' => $schema->string()
                ->description('The record ID or unique slug.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'record' => ['required', 'string'],
        ]);

        $entitySlug = $validated['entity'];
        $recordIdOrSlug = $validated['record'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $record = $this->recordService()->findOrFail($entitySlug, $recordIdOrSlug, $this->tenantId());

            return $this->asJson($this->recordService()->formatRecord($record));
        } catch (Throwable $e) {
            return "Error retrieving record [{$recordIdOrSlug}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
