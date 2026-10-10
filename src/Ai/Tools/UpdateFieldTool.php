<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\Concerns\InteractsWithFlexDomain;
use IvanMercedes\FlexFields\Support\ConditionEvaluator;
use IvanMercedes\FlexFields\Support\FieldTypes;
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
        return 'Update an existing custom field definition within an entity. Only the provided properties change; "settings" are merged with the existing ones (send a key with null to remove it).';
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
            'type' => $schema->string()
                ->description('New field type (changing the type does not convert stored values).')
                ->enum(FieldTypes::all()),
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
            'options' => $schema->object()
                ->description('Updated options dictionary for select/multiselect.'),
            'validation_rules' => $schema->array()
                ->items($schema->string())
                ->description('Updated list of Laravel validation rules.'),
            'category_ids' => $schema->array()
                ->items($schema->string())
                ->description('Array of category IDs, slugs, or names to assign this field to. Pass empty array [] to make the field available across all categories.'),
            'group' => $schema->string()
                ->description('Key or ID of a field group to move the field into. Pass an empty string to ungroup it.'),
            'settings' => $schema->object()
                ->description('Type-specific settings. Supported keys: '
                    . 'show_when {match: "all"|"any", conditions: [{field, operator, value}]} (conditional visibility; operators: ' . implode(', ', ConditionEvaluator::OPERATORS) . '); '
                    . 'related_entity (entity slug, required for relationship) and multiple (bool) for relationship/image/file; '
                    . 'translatable (bool, for text/textarea/richtext); phone_mask (e.g. "(999) 999-9999") for phone; '
                    . 'address_parts (subset of ' . implode(', ', FieldTypes::ADDRESS_PARTS) . ') for address; '
                    . 'schema (array of {label, key, type, width, is_required}) for repeater.'),
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
            'category_ids' => ['sometimes', 'nullable', 'array'],
            'type' => ['sometimes', 'string'],
            'group' => ['sometimes', 'nullable', 'string'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ]);

        $entitySlug = $validated['entity'];
        $fieldKey = $validated['field'];

        if ($guard = $this->guardEntityAllowed($entitySlug, 'structure')) {
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
                    'category_ids' => $field->category_ids ?? [],
                    'group' => $field->fieldGroup?->key,
                    'settings' => $field->settings ?? [],
                ],
            ]);
        } catch (Throwable $e) {
            return "Failed to update field [{$fieldKey}] on entity [{$entitySlug}]: {$this->describeException($e)}";
        }
    }
}
