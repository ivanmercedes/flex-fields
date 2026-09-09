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
class GetEntitySchemaTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Introspect and return the full schema of an entity, including all custom fields, field types, labels, required flags, options, repeater sub-fields, validation rules, and categories. Always call this before modifying an entity or adding/updating records.';
    }

    public function toolCategory(): AiToolCategory
    {
        return AiToolCategory::Read;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity to inspect.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $validated = $request->validate([
            'entity' => ['required', 'string'],
        ]);

        $entityIdOrSlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entityIdOrSlug)) {
            return $guard;
        }

        try {
            $schema = $this->entityService()->getSchema($entityIdOrSlug, $this->tenantId());

            return $this->asJson($schema);
        } catch (Throwable $e) {
            return "Error retrieving schema for entity [{$entityIdOrSlug}]: {$e->getMessage()}";
        }
    }
}
