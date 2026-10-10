<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Concerns;

use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Ai\AiContext;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Authorization\FlexPermissions;
use IvanMercedes\FlexFields\Services\CategoryDomainService;
use IvanMercedes\FlexFields\Services\EntityDomainService;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\FieldGroupDomainService;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Services\RecordHistoryService;
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

        return $e->getMessage();
    }

    protected function tenantId(): ?int
    {
        return $this->context()->getTenantId();
    }

    /**
     * Check whether mutations are allowed in the current context.
     */
    protected function guardAgainstReadOnly(): ?string
    {
        if ($this->context()->isReadOnly()) {
            return 'Operation rejected: FlexFields AI context is configured in read-only mode.';
        }

        return null;
    }

    /**
     * Check whether the target entity is allowed by the context whitelist and,
     * when permissions are enabled, whether the context user holds the ability
     * (a FlexPermissions ability such as "view_any", or "structure").
     */
    protected function guardEntityAllowed(string $entitySlug, ?string $ability = null): ?string
    {
        if (! $this->context()->isEntityAllowed($entitySlug)) {
            return "Operation rejected: Access to entity [{$entitySlug}] is not permitted by current AI context.";
        }

        if ($ability === 'structure') {
            return $this->guardStructure();
        }

        if ($ability !== null && FlexPermissions::enabled()) {
            $entity = $this->entityService()->find($entitySlug, $this->tenantId());

            if ($entity && ! FlexPermissions::allows($this->context()->getUser(), $ability, $entity)) {
                return 'Operation rejected: the current user lacks the [' . FlexPermissions::permissionName($ability, $entity) . '] permission.';
            }
        }

        return null;
    }

    /**
     * Structural changes (entities, fields, groups, categories) require the structure permission.
     */
    protected function guardStructure(): ?string
    {
        if (! FlexPermissions::allowsStructure($this->context()->getUser())) {
            return 'Operation rejected: the current user lacks the [' . FlexPermissions::structurePermission() . '] permission required for structural changes.';
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
