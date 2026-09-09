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
class UpdateCategoryTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Update an existing category/taxonomy entry on an entity.';
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
            'category' => $schema->string()
                ->description('The category slug or numeric ID to update.')
                ->required(),
            'name' => $schema->string()
                ->description('New name for the category.'),
            'slug' => $schema->string()
                ->description('New slug for the category.'),
            'parent_id' => $schema->integer()
                ->description('New parent category ID.'),
            'description' => $schema->string()
                ->description('New description.'),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'category' => ['required', 'string'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $entitySlug = $validated['entity'];
        $categoryIdOrSlug = $validated['category'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $cat = $this->categoryService()->update($entitySlug, $categoryIdOrSlug, $validated, $this->tenantId());

            return $this->asJson([
                'message' => "Category [{$cat->name}] updated on entity [{$entitySlug}].",
                'category' => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'parent_id' => $cat->parent_id,
                ],
            ]);
        } catch (Throwable $e) {
            return "Failed to update category [{$categoryIdOrSlug}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
