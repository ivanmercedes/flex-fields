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
class CreateFieldGroupTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Create a field group: a labeled, collapsible section that organizes custom fields in the record form. Optionally move existing fields into it.';
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
                ->description('Section title shown in the form (e.g. "SEO", "Pricing").')
                ->required(),
            'key' => $schema->string()
                ->description('Machine key of the group (snake_case). Generated from the label if omitted.'),
            'description' => $schema->string()
                ->description('Helper text shown under the section title.'),
            'is_collapsible' => $schema->boolean()
                ->description('Whether the section can be collapsed (default true).'),
            'is_collapsed' => $schema->boolean()
                ->description('Whether the section starts collapsed (default false).'),
            'fields' => $schema->array()
                ->items($schema->string())
                ->description('Keys of existing fields to move into this group.'),
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
            'key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_collapsible' => ['sometimes', 'boolean'],
            'is_collapsed' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'nullable', 'array'],
        ]);

        $entity = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entity, 'structure')) {
            return $guard;
        }

        try {
            $group = $this->fieldGroupService()->create($entity, $validated, $this->tenantId());

            return $this->asJson([
                'message' => "Field group [{$group->label}] (key: {$group->key}) created on entity [{$entity}].",
                'group' => $this->fieldGroupService()->format($group),
            ]);
        } catch (Throwable $e) {
            return "Failed to create field group on entity [{$entity}]: {$this->describeException($e)}";
        }
    }
}
