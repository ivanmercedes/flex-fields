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
class CreateFieldTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Add a new custom field to an entity. Supported types: ' . implode(', ', FieldTypes::all()) . '. '
            . 'For select/multiselect provide an options map (e.g. {"key": "Label"}). '
            . 'Relationship fields require settings.related_entity. Use settings.show_when for conditional visibility and "group" to place the field in a field group. '
            . 'Call GetEntitySchema first to know existing field keys and groups.';
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
            'label' => $schema->string()
                ->description('Human-readable field label (e.g. "Price", "Cover Image").')
                ->required(),
            'type' => $schema->string()
                ->description('Field type: ' . implode(', ', FieldTypes::all()) . '.')
                ->enum(FieldTypes::all())
                ->required(),
            'key' => $schema->string()
                ->description('Machine-readable field key (e.g. "price", "cover_image"). Auto-generated in snake_case from label if omitted.'),
            'description' => $schema->string()
                ->description('Helper or explanatory text displayed under the field in forms.'),
            'placeholder' => $schema->string()
                ->description('Placeholder text displayed inside input.'),
            'default_value' => $schema->string()
                ->description('Default value for new records.'),
            'is_required' => $schema->boolean()
                ->description('Whether this field is mandatory when creating or updating records.'),
            'is_searchable' => $schema->boolean()
                ->description('Whether this field is searchable in admin tables.'),
            'is_shown_in_list' => $schema->boolean()
                ->description('Whether this field should appear as a column in the record list table.'),
            'width' => $schema->string()
                ->description('Form column width: "full", "half", or "third". Defaults to "full".')
                ->enum(['full', 'half', 'third']),
            'options' => $schema->object()
                ->description('Options dictionary for select/multiselect types, e.g. {"red": "Red", "blue": "Blue"}.'),
            'validation_rules' => $schema->array()
                ->items($schema->string())
                ->description('List of Laravel validation rules, e.g. ["min:2", "max:100"].'),
            'category_ids' => $schema->array()
                ->items($schema->string())
                ->description('Optional array of category IDs, slugs, or names to assign this field to (scopes field visibility to records in these categories).'),
            'group' => $schema->string()
                ->description('Key or ID of an existing field group (section) to place the field in.'),
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
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string'],
            'key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'placeholder' => ['sometimes', 'nullable', 'string'],
            'default_value' => ['sometimes', 'nullable'],
            'is_required' => ['sometimes', 'boolean'],
            'is_searchable' => ['sometimes', 'boolean'],
            'is_shown_in_list' => ['sometimes', 'boolean'],
            'width' => ['sometimes', 'string', 'in:full,half,third'],
            'options' => ['sometimes', 'nullable', 'array'],
            'validation_rules' => ['sometimes', 'nullable', 'array'],
            'category_ids' => ['sometimes', 'nullable', 'array'],
            'group' => ['sometimes', 'nullable', 'string'],
            'settings' => ['sometimes', 'nullable', 'array'],
        ]);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug, 'structure')) {
            return $guard;
        }

        try {
            $field = $this->fieldService()->create($entitySlug, $validated, $this->tenantId());

            return $this->asJson([
                'message' => "Field [{$field->label}] (key: {$field->key}) created on entity [{$entitySlug}].",
                'field' => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'is_required' => (bool) $field->is_required,
                    'is_shown_in_list' => (bool) $field->is_shown_in_list,
                    'order' => $field->order,
                    'category_ids' => $field->category_ids ?? [],
                    'group' => $field->fieldGroup?->key,
                    'settings' => $field->settings ?? [],
                ],
            ]);
        } catch (Throwable $e) {
            return "Failed to create field on entity [{$entitySlug}]: {$this->describeException($e)}";
        }
    }
}
