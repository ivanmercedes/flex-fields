<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\Concerns\InteractsWithFlexDomain;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

#[Strict]
class DeleteCategoryTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Delete a category from an entity.';
    }

    /**
     * @return array<AiToolCategory>
     */
    public function toolCategory(): array
    {
        return [AiToolCategory::Destructive, AiToolCategory::Structure];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity.')
                ->required(),
            'category' => $schema->string()
                ->description('The category slug or numeric ID to delete.')
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
            'category' => ['required', 'string'],
        ]);

        $entitySlug = $validated['entity'];
        $categoryIdOrSlug = $validated['category'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $this->categoryService()->delete($entitySlug, $categoryIdOrSlug, $this->tenantId());

            return "Category [{$categoryIdOrSlug}] on entity [{$entitySlug}] was successfully deleted.";
        } catch (Throwable $e) {
            return "Failed to delete category [{$categoryIdOrSlug}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }

    protected function needsApproval(Request $request): Approval | bool
    {
        if (! $this->context()->shouldRequireApprovals()) {
            return false;
        }

        $entity = $request->string('entity');
        $category = $request->string('category');

        return Approval::required("Delete category [{$category}] from entity [{$entity}].");
    }
}
