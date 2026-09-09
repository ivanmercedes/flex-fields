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
class CreateCategoryTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Create a new category/taxonomy entry for an entity.';
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
            'name' => $schema->string()
                ->description('Name of the category.')
                ->required(),
            'slug' => $schema->string()
                ->description('Optional slug for the category (auto-generated from name if omitted).'),
            'parent_id' => $schema->integer()
                ->description('Optional parent category ID for hierarchical nesting.'),
            'description' => $schema->string()
                ->description('Optional category description.'),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $cat = $this->categoryService()->create($entitySlug, $validated, $this->tenantId());

            return $this->asJson([
                'message' => "Category [{$cat->name}] created for entity [{$entitySlug}].",
                'category' => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'parent_id' => $cat->parent_id,
                ],
            ]);
        } catch (Throwable $e) {
            return "Failed to create category on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
