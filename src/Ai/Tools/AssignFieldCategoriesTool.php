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
class AssignFieldCategoriesTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Assign one or more custom fields to specific categories within an entity, restricting field visibility to records in those categories, or pass an empty array [] to make fields available to all records across all categories.';
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
            'fields' => $schema->array()
                ->items($schema->string())
                ->description('Array of custom field keys or numeric IDs to assign (e.g. ["abv", "style"]).')
                ->required(),
            'categories' => $schema->array()
                ->items($schema->string())
                ->description('Array of category IDs, slugs, or names to assign to the fields. Pass an empty array [] to clear restrictions and make fields global.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['string'],
            'field' => ['sometimes', 'string'],
            'categories' => ['present', 'array'],
        ]);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        $fields = $validated['fields'] ?? ($request->has('field') ? [(string) $request->get('field')] : []);
        if (empty($fields)) {
            return "At least one field must be specified via 'fields' or 'field'.";
        }

        $categories = $validated['categories'];

        try {
            $updated = [];
            foreach ($fields as $fieldKey) {
                $field = $this->fieldService()->assignCategories(
                    entityIdOrSlug: $entitySlug,
                    fieldIdOrKey: $fieldKey,
                    categoryIdsOrSlugs: $categories,
                    tenantId: $this->tenantId()
                );

                $updated[] = [
                    'key' => $field->key,
                    'label' => $field->label,
                    'category_ids' => $field->category_ids ?? [],
                ];
            }

            $count = count($updated);
            $catCount = count($categories);
            $actionDesc = $catCount > 0
                ? "assigned to {$catCount} category/categories"
                : 'made global (category restrictions cleared)';

            return $this->asJson([
                'message' => "Successfully updated {$count} field(s) on entity [{$entitySlug}]: {$actionDesc}.",
                'fields' => $updated,
            ]);
        } catch (Throwable $e) {
            return "Failed to assign categories to fields on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
