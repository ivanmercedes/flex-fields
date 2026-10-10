<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\Concerns\InteractsWithFlexDomain;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Models\RecordRevision;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

#[Strict]
class RestoreRecordRevisionTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Restore a record to a previous version from its history (use ListRecordRevisions to find the revision ID). The current content is overwritten, but a new revision is recorded so it can be undone.';
    }

    public function toolCategory(): AiToolCategory
    {
        return AiToolCategory::Content;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity.')
                ->required(),
            'record' => $schema->string()
                ->description('The record ID or slug.')
                ->required(),
            'revision_id' => $schema->integer()
                ->description('ID of the revision to restore.')
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
            'record' => ['required', 'string'],
            'revision_id' => ['required', 'integer'],
        ]);

        $entity = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entity, FlexPermissions::UPDATE)) {
            return $guard;
        }

        try {
            $record = $this->recordService()->findOrFail($entity, $validated['record'], $this->tenantId(), withTrashed: true);

            // Scope the revision to the record so IDs of other records/tenants cannot be used.
            $revision = RecordRevision::query()
                ->where('entity_record_id', $record->id)
                ->findOrFail($validated['revision_id']);

            $restored = $this->historyService()->revert($revision, $this->context()->getUser());

            return $this->asJson([
                'message' => "Record [{$restored->id}] restored to revision [{$revision->id}].",
                'record' => $this->recordService()->formatRecord($restored),
            ]);
        } catch (Throwable $e) {
            return "Failed to restore revision [{$validated['revision_id']}] of record [{$validated['record']}]: {$this->describeException($e)}";
        }
    }

    protected function needsApproval(Request $request): Approval | bool
    {
        if (! $this->context()->shouldRequireApprovals()) {
            return false;
        }

        return Approval::required("Restore record [{$request->string('record')}] on entity [{$request->string('entity')}] to revision [{$request->integer('revision_id')}], overwriting its current content.");
    }
}
