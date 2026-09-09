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
class ListFieldsTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'List all custom fields defined on a given entity with their keys, types, labels, and configurations.';
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
        ];
    }

    public function handle(Request $request): string
    {
        $validated = $request->validate([
            'entity' => ['required', 'string'],
        ]);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $fields = $this->fieldService()->list($entitySlug, $this->tenantId());

            $result = $fields->map(function ($field) {
                return [
                    'id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'is_required' => (bool) $field->is_required,
                    'is_active' => (bool) $field->is_active,
                    'order' => $field->order,
                    'width' => $field->width,
                    'options' => $field->parsed_options,
                    'validation_rules' => $field->validation_rules,
                ];
            })->toArray();

            return $this->asJson([
                'entity' => $entitySlug,
                'count' => count($result),
                'fields' => $result,
            ]);
        } catch (Throwable $e) {
            return "Error listing fields for entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
