<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Ai\Contracts\Tool;

class FlexFieldsAiBuilder
{
    protected AiContext $context;

    /**
     * @var array<string>|null
     */
    protected ?array $categories = null;

    /**
     * @var array<string>
     */
    protected array $excludedCategories = [];

    /**
     * @var array<class-string<Tool>>
     */
    protected array $includedToolClasses = [];

    /**
     * @var array<class-string<Tool>>
     */
    protected array $excludedToolClasses = [];

    public function __construct(?AiContext $context = null)
    {
        $this->context = $context ?? new AiContext;
    }

    public function forTenant(int | string | null $tenant): self
    {
        $this->context->forTenant($tenant);

        return $this;
    }

    public function forUser(?Authenticatable $user): self
    {
        $this->context->forUser($user);

        return $this;
    }

    /**
     * @param  array<string>|string  $entities
     */
    public function allowEntities(array | string $entities): self
    {
        $this->context->allowEntities($entities);

        return $this;
    }

    public function readOnly(bool $readOnly = true): self
    {
        $this->context->readOnly($readOnly);

        return $this;
    }

    public function withApprovals(bool $require = true): self
    {
        $this->context->withApprovals($require);

        return $this;
    }

    public function withoutApprovals(): self
    {
        $this->context->withoutApprovals();

        return $this;
    }

    /**
     * Specify allowed tool categories (e.g. ['read', 'content']).
     *
     * @param  array<string|AiToolCategory>|string|AiToolCategory  $categories
     */
    public function categories(array | string | AiToolCategory $categories): self
    {
        $cats = is_array($categories) ? $categories : [$categories];
        $this->categories = array_map(fn ($c) => $c instanceof AiToolCategory ? $c->value : (string) $c, $cats);

        return $this;
    }

    /**
     * Exclude specific categories (e.g. ['destructive']).
     *
     * @param  array<string|AiToolCategory>|string|AiToolCategory  $categories
     */
    public function withoutCategories(array | string | AiToolCategory $categories): self
    {
        $cats = is_array($categories) ? $categories : [$categories];
        $this->excludedCategories = array_merge(
            $this->excludedCategories,
            array_map(fn ($c) => $c instanceof AiToolCategory ? $c->value : (string) $c, $cats)
        );

        return $this;
    }

    /**
     * Exclude specific tool classes or tool names.
     *
     * @param  array<class-string<Tool>>  $toolClasses
     */
    public function exclude(array $toolClasses): self
    {
        $this->excludedToolClasses = array_merge($this->excludedToolClasses, $toolClasses);

        return $this;
    }

    /**
     * Get all resolved and configured tools.
     *
     * @return array<Tool>
     */
    public function tools(): array
    {
        return FlexFieldsAi::resolveTools(
            categories: $this->categories,
            excludedCategories: $this->excludedCategories,
            excludedToolClasses: $this->excludedToolClasses,
            context: $this->context
        );
    }

    /**
     * Get only Read category tools.
     *
     * @return array<Tool>
     */
    public function readTools(): array
    {
        return $this->categories(AiToolCategory::Read)->tools();
    }

    /**
     * Get only Structure category tools.
     *
     * @return array<Tool>
     */
    public function structureTools(): array
    {
        return $this->categories(AiToolCategory::Structure)->tools();
    }

    /**
     * Get only Content category tools.
     *
     * @return array<Tool>
     */
    public function contentTools(): array
    {
        return $this->categories(AiToolCategory::Content)->tools();
    }

    /**
     * Get only Publishing category tools.
     *
     * @return array<Tool>
     */
    public function publishingTools(): array
    {
        return $this->categories(AiToolCategory::Publishing)->tools();
    }

    /**
     * Get only Destructive category tools.
     *
     * @return array<Tool>
     */
    public function destructiveTools(): array
    {
        return $this->categories(AiToolCategory::Destructive)->tools();
    }

    public function getContext(): AiContext
    {
        return $this->context;
    }
}
