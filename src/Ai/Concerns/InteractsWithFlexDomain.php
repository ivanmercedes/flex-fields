<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Concerns;

use IvanMercedes\FlexFields\Ai\AiContext;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use IvanMercedes\FlexFields\Services\CategoryDomainService;
use IvanMercedes\FlexFields\Services\EntityDomainService;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Services\RecordDomainService;

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
     * Check whether the target entity is allowed by the context whitelist.
     */
    protected function guardEntityAllowed(string $entitySlug): ?string
    {
        if (! $this->context()->isEntityAllowed($entitySlug)) {
            return "Operation rejected: Access to entity [{$entitySlug}] is not permitted by current AI context.";
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
