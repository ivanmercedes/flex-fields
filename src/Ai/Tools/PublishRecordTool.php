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
class PublishRecordTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'Publish a content record, changing its status to "published".';
    }

    /**
     * @return array<AiToolCategory>
     */
    public function toolCategory(): array
    {
        return [AiToolCategory::Publishing, AiToolCategory::Content];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()
                ->description('The slug or numeric ID of the entity.')
                ->required(),
            'record' => $schema->string()
                ->description('The record ID or unique slug to publish.')
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
        ]);

        $entitySlug = $validated['entity'];
        $recordIdOrSlug = $validated['record'];

        if ($guard = $this->guardEntityAllowed($entitySlug)) {
            return $guard;
        }

        try {
            $record = $this->recordService()->publish($entitySlug, $recordIdOrSlug, $this->tenantId());

            return $this->asJson([
                'message' => "Record [{$record->title}] (ID: {$record->id}) is now published.",
                'record' => [
                    'id' => $record->id,
                    'title' => $record->title,
                    'slug' => $record->slug,
                    'status' => $record->status,
                ],
            ]);
        } catch (Throwable $e) {
            return "Failed to publish record [{$recordIdOrSlug}] on entity [{$entitySlug}]: {$e->getMessage()}";
        }
    }
}
