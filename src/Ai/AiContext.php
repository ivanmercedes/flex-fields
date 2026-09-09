<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AiContext
{
    /**
     * @param  array<string>|null  $allowedEntities
     */
    public function __construct(
        protected ?int $tenantId = null,
        protected ?Authenticatable $user = null,
        protected ?array $allowedEntities = null,
        protected bool $readOnly = false,
        protected bool $requireApprovals = true,
    ) {
        if ($this->tenantId === null) {
            $this->tenantId = $this->resolveCurrentTenantId();
        }

        if ($this->user === null && Auth::check()) {
            $this->user = Auth::user();
        }

        if ($this->readOnly === false && config('flex-fields.ai.read_only', false)) {
            $this->readOnly = true;
        }

        if ($this->requireApprovals === true && ! config('flex-fields.ai.require_approvals', true)) {
            $this->requireApprovals = false;
        }

        if ($this->allowedEntities === null && config('flex-fields.ai.allowed_entities') !== null) {
            $this->allowedEntities = (array) config('flex-fields.ai.allowed_entities');
        }
    }

    public static function make(): self
    {
        return new self;
    }

    public function forTenant(int | string | null $tenant): self
    {
        if (is_object($tenant) && method_exists($tenant, 'getKey')) {
            $this->tenantId = (int) $tenant->getKey();
        } elseif ($tenant !== null) {
            $this->tenantId = (int) $tenant;
        } else {
            $this->tenantId = null;
        }

        return $this;
    }

    public function forUser(?Authenticatable $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Whitelist allowed entity slugs that the AI is permitted to interact with.
     *
     * @param  array<string>|string  $entities
     */
    public function allowEntities(array | string $entities): self
    {
        $this->allowedEntities = (array) $entities;

        return $this;
    }

    public function readOnly(bool $readOnly = true): self
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    public function withApprovals(bool $require = true): self
    {
        $this->requireApprovals = $require;

        return $this;
    }

    public function withoutApprovals(): self
    {
        $this->requireApprovals = false;

        return $this;
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getUser(): ?Authenticatable
    {
        return $this->user;
    }

    /**
     * @return array<string>|null
     */
    public function getAllowedEntities(): ?array
    {
        return $this->allowedEntities;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function shouldRequireApprovals(): bool
    {
        return $this->requireApprovals;
    }

    /**
     * Check whether an entity slug is allowed by the context whitelist.
     */
    public function isEntityAllowed(string $entitySlug): bool
    {
        if ($this->allowedEntities === null) {
            return true;
        }

        return in_array($entitySlug, $this->allowedEntities, true);
    }

    /**
     * Auto-detect tenant ID if running inside Filament tenancy context.
     */
    protected function resolveCurrentTenantId(): ?int
    {
        try {
            if (class_exists(Filament::class) && Filament::hasTenancy()) {
                $tenant = Filament::getTenant();

                return $tenant ? (int) $tenant->getKey() : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
