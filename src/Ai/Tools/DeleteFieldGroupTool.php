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
class DeleteFieldGroupTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Delete a field group (form section). The fields inside it are NOT deleted; they become ungrouped.';
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
            'group' => $schema->string()
                ->description('Key or numeric ID of the field group to delete.')
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
            'group' => ['required', 'string'],
        ]);

        $entity = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entity, 'structure')) {
            return $guard;
        }

        try {
            $this->fieldGroupService()->delete($entity, $validated['group'], $this->tenantId());

            return "Field group [{$validated['group']}] on entity [{$entity}] was deleted. Its fields are now ungrouped.";
        } catch (Throwable $e) {
            return "Failed to delete field group [{$validated['group']}] on entity [{$entity}]: {$this->describeException($e)}";
        }
    }

    protected function needsApproval(Request $request): Approval | bool
    {
        if (! $this->context()->shouldRequireApprovals()) {
            return false;
        }

        return Approval::required("Delete field group [{$request->string('group')}] from entity [{$request->string('entity')}]. Its fields will be kept but ungrouped.");
    }
}
