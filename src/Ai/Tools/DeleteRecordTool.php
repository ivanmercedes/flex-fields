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
class DeleteRecordTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Delete a content record from an entity. By default soft-deletes the record; optionally force-deletes permanently.';
    }

    /**
     * @return array<AiToolCategory>
     */
    public function toolCategory(): array
    {
        return [AiToolCategory::Destructive, AiToolCategory::Content];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity.')
                ->required(),
            'record' => $schema->string()
                ->description('The record ID or unique slug to delete.')
                ->required(),
            'force' => $schema->boolean()
                ->description('If true, permanently delete the record and its values. If false, soft-delete.'),
        ];
    }

    public function handle(Request $request): string
    {
        if ($guard = $this->guardAgainstReadOnly()) {
            return $guard;
        }

        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'record' => ['required', 'string'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $entitySlug = $validated['entity'];
        $recordIdOrSlug = $validated['record'];
        $force = (bool) ($validated['force'] ?? false);

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $this->recordService()->delete($entitySlug, $recordIdOrSlug, $force, $this->tenantId());

            $action = $force ? 'permanently deleted' : 'soft-deleted';

            return "Record [{$recordIdOrSlug}] on entity [{$entitySlug}] was {$action}.";
        } catch (Throwable $e) {
            return "Failed to delete record [{$recordIdOrSlug}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }

    protected function needsApproval(Request $request): Approval | bool
    {
        if (! $this->context()->shouldRequireApprovals()) {
            return false;
        }

        $entity = $request->string('entity');
        $record = $request->string('record');
        $force = (bool) $request->boolean('force');

        $type = $force ? 'Permanently delete' : 'Delete';

        return Approval::required("{$type} record [{$record}] from entity [{$entity}].");
    }
}
