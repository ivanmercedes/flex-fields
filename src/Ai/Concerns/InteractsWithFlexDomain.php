<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Ai\AiContext;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Ai\ToolResult;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Services\CategoryDomainService;
use IvanMercedes\FlexFields\Services\EntityDomainService;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\FieldGroupDomainService;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Services\RecordHistoryService;
use Laravel\Ai\Tools\Request;
use Throwable;

trait InteractsWithFlexDomain
{
    protected AiContext $context;

    protected EntityDomainService $entityService;

    protected FieldDomainService $fieldService;

    protected CategoryDomainService $categoryService;

    protected RecordDomainService $recordService;

    /**
     * Return the category for this tool.
     *
     * @return array<AiToolCategory>|AiToolCategory
     */
    abstract public function toolCategory(): array | AiToolCategory;

    /**
     * Perform the tool's operation. Failures are returned as ToolResult::error().
     */
    abstract protected function execute(Request $request): ToolResult | string;

    /**
     * Laravel AI SDK entry point: the result as text for the model.
     */
    public function handle(Request $request): string
    {
        return (string) $this->run($request);
    }

    /**
     * Run the tool and get a typed result that tells failures apart.
     * Invalid arguments still throw a ValidationException.
     */
    public function run(Request $request): ToolResult
    {
        $result = $this->execute($request);

        return $result instanceof ToolResult ? $result : ToolResult::success($result);
    }

    /**
     * Snake-case identifier of the tool, e.g. "get_entity_schema".
     */
    public function toolName(): string
    {
        return Str::of(class_basename($this))->beforeLast('Tool')->snake()->toString();
    }

    /**
     * Human readable title of the tool, e.g. "Get Entity Schema".
     */
    public function toolTitle(): string
    {
        return Str::of(class_basename($this))->beforeLast('Tool')->headline()->toString();
    }

    public function setContext(AiContext $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function context(): AiContext
    {
        return $this->context ??= new AiContext;
    }

    protected function entityService(): EntityDomainService
    {
        return $this->entityService ??= new EntityDomainService;
    }

    protected function fieldService(): FieldDomainService
    {
        return $this->fieldService ??= new FieldDomainService($this->entityService());
    }

    protected function categoryService(): CategoryDomainService
    {
        return $this->categoryService ??= new CategoryDomainService($this->entityService());
    }

    protected function recordService(): RecordDomainService
    {
        return $this->recordService ??= new RecordDomainService($this->entityService());
    }

    protected function fieldGroupService(): FieldGroupDomainService
    {
        return new FieldGroupDomainService($this->entityService());
    }

    protected function historyService(): RecordHistoryService
    {
        return app(RecordHistoryService::class);
    }

    protected function error(string $message): ToolResult
    {
        return ToolResult::error($message);
    }

    /**
     * Turn a validation exception into a readable message for the model.
     */
    protected function describeException(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return collect($e->errors())
                ->map(fn (array $messages, string $key) => "{$key}: " . implode(' ', $messages))
                ->implode('; ');
        }

        // Database errors carry SQL and connection details: never hand them to the model.
        if ($e instanceof QueryException) {
            report($e);

            return 'The operation could not be completed because of a database error.';
        }

        return $e->getMessage();
    }

    protected function tenantId(): ?int
    {
        return $this->context()->getTenantId();
    }

    /**
     * Check whether mutations are allowed in the current context.
     */
    protected function guardAgainstReadOnly(): ?ToolResult
    {
        if ($this->context()->isReadOnly()) {
            return $this->error('Operation rejected: FlexFields AI context is configured in read-only mode.');
        }

        return null;
    }

    /**
     * A relationship field exposes the records of its related entity, so a context
     * limited to some entities cannot point a field at another one.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function guardRelatedEntityAllowed(array $attributes): ?ToolResult
    {
        $related = $attributes['settings']['related_entity'] ?? null;

        if (is_string($related) && $related !== '' && ! $this->context()->isEntityAllowed($related)) {
            return $this->error("Operation rejected: Access to entity [{$related}] is not permitted by current AI context.");
        }

        return null;
    }

    /**
     * Check whether the target entity is allowed by the context whitelist and,
     * when permissions are enabled, whether the context user holds the ability
     * (a FlexPermissions ability such as "view_any", or "structure").
     */
    protected function guardEntityAllowed(string $entitySlug, ?string $ability = null): ?ToolResult
    {
        if (! $this->context()->isEntityAllowed($entitySlug)) {
            return $this->error("Operation rejected: Access to entity [{$entitySlug}] is not permitted by current AI context.");
        }

        if ($ability === 'structure') {
            return $this->guardStructure();
        }

        if ($ability !== null && FlexPermissions::enabled()) {
            $entity = $this->entityService()->find($entitySlug, $this->tenantId());

            if ($entity && ! FlexPermissions::allows($this->context()->getUser(), $ability, $entity)) {
                return $this->error('Operation rejected: the current user lacks the [' . FlexPermissions::permissionName($ability, $entity) . '] permission.');
            }
        }

        return null;
    }

    /**
     * Structural changes (entities, fields, groups, categories) require the structure permission.
     */
    protected function guardStructure(): ?ToolResult
    {
        if (! FlexPermissions::allowsStructure($this->context()->getUser())) {
            return $this->error('Operation rejected: the current user lacks the [' . FlexPermissions::structurePermission() . '] permission required for structural changes.');
        }

        return null;
    }

    /**
     * Format a response array as readable JSON.
     */
    protected function asJson(mixed $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
