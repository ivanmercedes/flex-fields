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
class UpdateEntityTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Update attributes and settings of an existing entity.';
    }

    public function toolCategory(): AiToolCategory
    {
        return AiToolCategory::Structure;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity to update.')
                ->required(),
            'name' => $schema->string()
                ->description('New name for the entity.'),
            'slug' => $schema->string()
                ->description('New slug for the entity (must be unique).'),
            'description' => $schema->string()
                ->description('New description for the entity.'),
            'icon' => $schema->string()
                ->description('New Heroicon name.'),
            'color' => $schema->string()
                ->description('New hex color code.'),
            'is_active' => $schema->boolean()
                ->description('Whether the entity is active.'),
            'show_in_menu' => $schema->boolean()
                ->description('Whether to display in the sidebar menu.'),
            'menu_order' => $schema->integer()
                ->description('New menu sorting order.'),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'show_in_menu' => ['sometimes', 'boolean'],
            'menu_order' => ['sometimes', 'integer'],
        ]);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $entity = $this->entityService()->update($entitySlug, $validated, $this->tenantId());

            return $this->asJson([
                'message' => "Entity [{$entity->name}] successfully updated.",
                'entity' => [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'slug' => $entity->slug,
                    'description' => $entity->description,
                    'icon' => $entity->icon,
                    'color' => $entity->color,
                    'is_active' => (bool) $entity->is_active,
                ],
            ]);
        } catch (Throwable $e) {
            return "Failed to update entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
