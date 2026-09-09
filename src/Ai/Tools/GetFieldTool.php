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
class GetFieldTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Get detailed configuration and metadata for a specific custom field within an entity.';
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
            'field' => $schema->string()
                ->description('The field key or numeric ID.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'field' => ['required', 'string'],
        ]);

        $entitySlug = $validated['entity'];
        $fieldKey = $validated['field'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $field = $this->fieldService()->findOrFail($entitySlug, $fieldKey, $this->tenantId());

            return $this->asJson([
                'id' => $field->id,
                'entity_id' => $field->entity_id,
                'entity_slug' => $field->entity->slug ?? null,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'description' => $field->description,
                'placeholder' => $field->placeholder,
                'default_value' => $field->default_value,
                'is_required' => (bool) $field->is_required,
                'is_active' => (bool) $field->is_active,
                'is_searchable' => (bool) $field->is_searchable,
                'is_shown_in_list' => (bool) $field->is_shown_in_list,
                'width' => $field->width,
                'order' => $field->order,
                'options' => $field->parsed_options,
                'validation_rules' => $field->validation_rules ?? [],
                'settings' => $field->settings ?? [],
                'category_ids' => $field->category_ids ?? [],
            ]);
        } catch (Throwable $e) {
            return "Error retrieving field [{$fieldKey}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
