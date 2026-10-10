<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\Concerns\InteractsWithFlexDomain;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

#[Strict]
class ListRecordRevisionsTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Show the change history (audit log) of a record: who changed what and when, newest first. Each revision has an ID that can be used to restore that version.';
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
            'record' => $schema->string()
                ->description('The record ID or slug.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of revisions to return (1-50, default 10).'),
            'with_snapshot' => $schema->boolean()
                ->description('Include the full record snapshot of each revision.'),
        ];
    }

    public function handle(Request $request): string
    {
        $validated = $request->validate([
            'entity' => ['required', 'string'],
            'record' => ['required', 'string'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'with_snapshot' => ['sometimes', 'boolean'],
        ]);

        $entity = $validated['entity'];

        if ($guard = $this->guardEntityAllowed($entity, FlexPermissions::VIEW)) {
            return $guard;
        }

        try {
            $record = $this->recordService()->findOrFail($entity, $validated['record'], $this->tenantId(), withTrashed: true);
            $revisions = $this->historyService()->revisions($record, (int) ($validated['limit'] ?? 10));

            return $this->asJson([
                'record_id' => $record->id,
                'count' => $revisions->count(),
                'revisions' => $revisions->map(fn ($revision) => $this->historyService()->format($revision, (bool) ($validated['with_snapshot'] ?? false)))->all(),
            ]);
        } catch (Throwable $e) {
            return "Error reading history of record [{$validated['record']}] on entity [{$entity}]: {$this->describeException($e)}";
        }
    }
}
