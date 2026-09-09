<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\Concerns\InteractsWithFlexDomain;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

#[Strict]
class ListEntitiesTool implements Tool
{
    use InteractsWithFlexDomain;

    public function description(): string
    {
        return 'List all dynamic entities defined in FlexFields with their slugs, names, descriptions, and active statuses.';
    }

    public function toolCategory(): AiToolCategory
    {
        return AiToolCategory::Read;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'active_only' => $schema->boolean()
                ->description('If true, only returns active entities. If false, returns all.'),
            'search' => $schema->string()
                ->description('Optional search term to filter entities by name, slug, or description.'),
        ];
    }

    public function handle(Request $request): string
    {
        $validated = $request->validate([
            'active_only' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string'],
        ]);

        $entities = $this->entityService()->list($validated, $this->tenantId());

        $allowed = $this->context()->getAllowedEntities();
        if ($allowed !== null) {
            $entities = $entities->filter(fn ($e) => in_array($e->slug, $allowed, true))->values();
        }

        $result = $entities->map(function ($entity) {
            return [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'description' => $entity->description,
                'icon' => $entity->icon,
                'color' => $entity->color,
                'is_active' => (bool) $entity->is_active,
                'show_in_menu' => (bool) $entity->show_in_menu,
                'menu_order' => $entity->menu_order,
                'fields_count' => $entity->customFields()->count(),
                'records_count' => $entity->records()->count(),
            ];
        })->toArray();

        return $this->asJson([
            'count' => count($result),
            'entities' => $result,
        ]);
    }
}
