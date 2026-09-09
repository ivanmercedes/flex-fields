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
class DeleteEntityTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Permanently delete an entity along with all its custom fields, categories, and content records. This action is destructive and cannot be undone.';
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
                ->description('The slug or numeric ID of the entity to permanently delete.')
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
        ]);

        $entitySlug = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $this->entityService()->delete($entitySlug, $this->tenantId());

            return "Entity [{$entitySlug}] and all associated data were permanently deleted.";
        } catch (Throwable $e) {
            return "Failed to delete entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }

    protected function needsApproval(Request $request): Approval | bool
    {
        if (! $this->context()->shouldRequireApprovals()) {
            return false;
        }

        $entity = $request->string('entity');

        return Approval::required("Permanently delete entity [{$entity}] and all of its associated fields, records, and categories. This cannot be undone.");
    }
}
