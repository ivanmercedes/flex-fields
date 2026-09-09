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
class CreateEntityTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Create a new dynamic entity structure (data collection / post type) in FlexFields.';
    }

    public function toolCategory(): AiToolCategory
    {
        return AiToolCategory::Structure;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The human-readable name of the entity, e.g. "Product" or "Customer Review".')
                ->required(),
            'slug' => $schema->string()
                ->description('Optional unique slug identifier. If omitted, will be auto-generated from name.'),
            'description' => $schema->string()
                ->description('Optional description of what this entity represents.'),
            'icon' => $schema->string()
                ->description('Optional Heroicon name, e.g. "heroicon-o-shopping-bag". Defaults to "heroicon-o-cube".'),
            'color' => $schema->string()
                ->description('Optional hex color code for UI badges, e.g. "#3b82f6".'),
            'is_active' => $schema->boolean()
                ->description('Whether the entity is active. Defaults to true.'),
            'show_in_menu' => $schema->boolean()
                ->description('Whether to display this entity in the admin sidebar. Defaults to true.'),
            'menu_order' => $schema->integer()
                ->description('Sorting order in navigation menu. Defaults to 0.'),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'show_in_menu' => ['sometimes', 'boolean'],
            'menu_order' => ['sometimes', 'integer'],
        ]);

        try {
            $entity = $this->entityService()->create($validated, $this->tenantId());

            return $this->asJson([
                'message' => "Entity [{$entity->name}] successfully created with slug [{$entity->slug}].",
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
            return "Failed to create entity: {$e->getMessage()}";
        }
    }
}
