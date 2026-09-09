<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai;

use IvanMercedes\FlexFields\Ai\Tools\AssignFieldCategoriesTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateCategoryTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\CreateRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteCategoryTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\DeleteRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\GetEntitySchemaTool;
use IvanMercedes\FlexFields\Ai\Tools\GetFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\GetRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\ListCategoriesTool;
use IvanMercedes\FlexFields\Ai\Tools\ListEntitiesTool;
use IvanMercedes\FlexFields\Ai\Tools\ListFieldsTool;
use IvanMercedes\FlexFields\Ai\Tools\ListRecordsTool;
use IvanMercedes\FlexFields\Ai\Tools\PublishRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\SetRecordValuesTool;
use IvanMercedes\FlexFields\Ai\Tools\UnpublishRecordTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateCategoryTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateEntityTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateFieldTool;
use IvanMercedes\FlexFields\Ai\Tools\UpdateRecordTool;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use RuntimeException;

class FlexFieldsAi
{
    /**
     * Complete registry of all FlexFields AI Tools mapped to their class names.
     *
     * @var array<class-string<Tool>>
     */
    protected static array $toolRegistry = [
        // Read
        ListEntitiesTool::class,
        GetEntitySchemaTool::class,
        ListFieldsTool::class,
        GetFieldTool::class,
        ListCategoriesTool::class,
        ListRecordsTool::class,
        GetRecordTool::class,

        // Structure
        CreateEntityTool::class,
        UpdateEntityTool::class,
        CreateFieldTool::class,
        UpdateFieldTool::class,
        AssignFieldCategoriesTool::class,
        CreateCategoryTool::class,
        UpdateCategoryTool::class,
        DeleteEntityTool::class,
        DeleteFieldTool::class,
        DeleteCategoryTool::class,

        // Content
        CreateRecordTool::class,
        UpdateRecordTool::class,
        SetRecordValuesTool::class,
        DeleteRecordTool::class,

        // Publishing
        PublishRecordTool::class,
        UnpublishRecordTool::class,
    ];

    /**
     * Determine whether the Laravel AI SDK is installed and available.
     */
    public static function isAvailable(): bool
    {
        return interface_exists(Tool::class);
    }

    /**
     * Ensure the Laravel AI SDK is available or throw a helpful exception.
     *
     * @throws RuntimeException
     */
    public static function ensureAvailable(): void
    {
        if (! static::isAvailable()) {
            throw new RuntimeException(
                'The Laravel AI SDK is required to use FlexFields AI Tools. ' .
                'Please install it via Composer: composer require laravel/ai'
            );
        }
    }

    /**
     * Start a fluent configuration builder for FlexFields AI tools.
     */
    public static function configure(?AiContext $context = null): FlexFieldsAiBuilder
    {
        static::ensureAvailable();

        return new FlexFieldsAiBuilder($context);
    }

    /**
     * Get an array of FlexFields AI tools, optionally filtered by categories.
     *
     * @param  array<string|AiToolCategory>|string|AiToolCategory|null  $categories
     * @return array<Tool>
     */
    public static function tools(array | string | AiToolCategory | null $categories = null, ?AiContext $context = null): array
    {
        static::ensureAvailable();

        $builder = static::configure($context);

        if ($categories !== null) {
            $builder->categories($categories);
        }

        return $builder->tools();
    }

    /**
     * Get all Read category tools.
     *
     * @return array<Tool>
     */
    public static function readTools(?AiContext $context = null): array
    {
        return static::tools(AiToolCategory::Read, $context);
    }

    /**
     * Get all Structure category tools.
     *
     * @return array<Tool>
     */
    public static function structureTools(?AiContext $context = null): array
    {
        return static::tools(AiToolCategory::Structure, $context);
    }

    /**
     * Get all Content category tools.
     *
     * @return array<Tool>
     */
    public static function contentTools(?AiContext $context = null): array
    {
        return static::tools(AiToolCategory::Content, $context);
    }

    /**
     * Get all Publishing category tools.
     *
     * @return array<Tool>
     */
    public static function publishingTools(?AiContext $context = null): array
    {
        return static::tools(AiToolCategory::Publishing, $context);
    }

    /**
     * Get all Destructive category tools.
     *
     * @return array<Tool>
     */
    public static function destructiveTools(?AiContext $context = null): array
    {
        return static::tools(AiToolCategory::Destructive, $context);
    }

    /**
     * Get all available tools without any category filtering.
     *
     * @return array<Tool>
     */
    public static function allTools(?AiContext $context = null): array
    {
        return static::tools(null, $context);
    }

    /**
     * Resolve tool instances based on criteria.
     *
     * @param  array<string>|null  $categories
     * @param  array<string>  $excludedCategories
     * @param  array<class-string<Tool>>  $excludedToolClasses
     * @return array<Tool>
     */
    public static function resolveTools(
        ?array $categories = null,
        array $excludedCategories = [],
        array $excludedToolClasses = [],
        ?AiContext $context = null
    ): array {
        static::ensureAvailable();

        $context ??= new AiContext;
        $tools = [];

        foreach (static::$toolRegistry as $toolClass) {
            if (in_array($toolClass, $excludedToolClasses, true)) {
                continue;
            }

            /** @var Tool $tool */
            $tool = new $toolClass;

            if (method_exists($tool, 'setContext')) {
                $tool->setContext($context);
            }

            // Check approvals
            if (! $context->shouldRequireApprovals() && $tool instanceof Approvable) {
                $tool->withoutApproval();
            }

            // Check category matching
            if (method_exists($tool, 'toolCategory')) {
                $toolCats = (array) $tool->toolCategory();
                $toolCatValues = array_map(fn ($c) => $c instanceof AiToolCategory ? $c->value : (string) $c, $toolCats);

                // Check exclusions
                if (! empty($excludedCategories) && ! empty(array_intersect($toolCatValues, $excludedCategories))) {
                    continue;
                }

                // Check inclusions
                if ($categories !== null && empty(array_intersect($toolCatValues, $categories))) {
                    continue;
                }
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Register an additional custom tool class in the registry.
     *
     * @param  class-string<Tool>  $toolClass
     */
    public static function registerTool(string $toolClass): void
    {
        if (! in_array($toolClass, static::$toolRegistry, true)) {
            static::$toolRegistry[] = $toolClass;
        }
    }

    /**
     * Get the full list of registered tool classes.
     *
     * @return array<class-string<Tool>>
     */
    public static function getRegisteredToolClasses(): array
    {
        return static::$toolRegistry;
    }
}
