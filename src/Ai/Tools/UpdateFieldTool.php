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
class UpdateFieldTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Update an existing custom field definition within an entity.';
    }

    public function toolCategory(): AiToolCategory
    {
        return AiToolCategory::Structure;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity.')
                ->required(),
            'field' => $schema->string()
                ->description('The field key or numeric ID to update.')
                ->required(),
            'label' => $schema->string()
                ->description('New label for the field.'),
            'key' => $schema->string()
                ->description('New key identifier for the field.'),
            'description' => $schema->string()
                ->description('New description or helper text.'),
            'placeholder' => $schema->string()
                ->description('New placeholder text.'),
            'default_value' => $schema->string()
                ->description('New default value.'),
            'is_required' => $schema->boolean()
                ->description('Whether the field is required.'),
            'is_active' => $schema->boolean()
                ->description('Whether the field is active.'),
            'is_searchable' => $schema->boolean()
                ->description('Whether the field is searchable.'),
            'is_shown_in_list' => $schema->boolean()
                ->description('Whether the field is shown in the records list table.'),
            'width' => $schema->string()
                ->description('Column width: full, half, or third.')
                ->enum(['full', 'half', 'third']),
            'options' => $schema->array()
                ->description('Updated options array for select/multiselect.'),
            'validation_rules' => $schema->array()
                ->description('Updated list of Laravel validation rules.'),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'field' => ['required', 'string'],
            'label' => ['sometimes', 'string', 'max:255'],
            'key' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'placeholder' => ['sometimes', 'nullable', 'string'],
            'default_value' => ['sometimes', 'nullable'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'is_shown_in_list' => ['sometimes', 'boolean'],
            'width' => ['sometimes', 'string', 'in:full,half,third'],
            'options' => ['sometimes', 'nullable', 'array'],
            'validation_rules' => ['sometimes', 'nullable', 'array'],
        ]);

        $entitySlug = $validated['entity'];
        $fieldKey = $validated['field'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $field = $this->fieldService()->update($entitySlug, $fieldKey, $validated, $this->tenantId());

            return $this->asJson([
                'message' => "Field [{$field->key}] on entity [{$entitySlug}] successfully updated.",
                'field' => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'is_required' => (bool) $field->is_required,
                    'is_active' => (bool) $field->is_active,
                ],
            ]);
        } catch (Throwable $e) {
            return "Failed to update field [{$fieldKey}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
