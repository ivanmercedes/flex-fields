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
class ListCategoriesTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'List all categories and taxonomies defined for a given entity.';
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
        ];
    }

    public function handle(Request $request): string
    {
        $validated = $request->validate([
            'entity' => ['required', 'string'],
        ]);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $categories = $this->categoryService()->list($entitySlug, $this->tenantId());

            $result = $categories->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'parent_id' => $cat->parent_id,
                    'description' => $cat->description,
                    'children_count' => $cat->children->count(),
                ];
            })->toArray();

            return $this->asJson([
                'entity' => $entitySlug,
                'count' => count($result),
                'categories' => $result,
            ]);
        } catch (Throwable $e) {
            return "Error listing categories for entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
