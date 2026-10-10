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
class UpdateFieldGroupTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Update a field group (label, key, description, order, collapsible state) and/or move fields into it.';
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
            'group' => $schema->string()
                ->description('Key or numeric ID of the field group to update.')
                ->required(),
            'label' => $schema->string()->description('New section title.'),
            'key' => $schema->string()->description('New machine key.'),
            'description' => $schema->string()->description('New helper text.'),
            'order' => $schema->integer()->description('Position of the section in the form.'),
            'is_collapsible' => $schema->boolean()->description('Whether the section can be collapsed.'),
            'is_collapsed' => $schema->boolean()->description('Whether the section starts collapsed.'),
            'fields' => $schema->array()
                ->items($schema->string())
                ->description('Keys of fields to move into this group.'),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'group' => ['required', 'string'],
            'label' => ['sometimes', 'string', 'max:255'],
            'key' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'order' => ['sometimes', 'integer'],
            'is_collapsible' => ['sometimes', 'boolean'],
            'is_collapsed' => ['sometimes', 'boolean'],
            'fields' => ['sometimes', 'nullable', 'array'],
        ]);

        $entity = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entity, 'structure')) {
            return $guard;
        }

        try {
            $attributes = array_diff_key($validated, array_flip(['entity', 'group']));
            $group = $this->fieldGroupService()->update($entity, $validated['group'], $attributes, $this->tenantId());

            return $this->asJson([
                'message' => "Field group [{$group->key}] on entity [{$entity}] updated.",
                'group' => $this->fieldGroupService()->format($group),
            ]);
        } catch (Throwable $e) {
            return "Failed to update field group [{$validated['group']}] on entity [{$entity}]: {$this->describeException($e)}";
        }
    }
}
