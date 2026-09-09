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
class DeleteFieldTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Permanently delete a custom field from an entity and all stored values for that field. This action is destructive and cannot be undone.';
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
            'field' => $schema->string()
                ->description('The field key or numeric ID to permanently delete.')
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
            'field' => ['required', 'string'],
        ]);

        $entitySlug = $validated['entity'];
        $fieldKey = $validated['field'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $this->fieldService()->delete($entitySlug, $fieldKey, $this->tenantId());

            return "Field [{$fieldKey}] on entity [{$entitySlug}] and all associated values were permanently deleted.";
        } catch (Throwable $e) {
            return "Failed to delete field [{$fieldKey}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }

    protected function needsApproval(Request $request): Approval | bool
    {
        if (! $this->context()->shouldRequireApprovals()) {
            return false;
        }

        $entity = $request->string('entity');
        $field = $request->string('field');

        return Approval::required("Permanently delete field [{$field}] from entity [{$entity}] and erase all of its saved values across records. This cannot be undone.");
    }
}
