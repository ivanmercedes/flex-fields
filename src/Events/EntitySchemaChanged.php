<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Events;

use Illuminate\Foundation\Events\Dispatchable;
use IvanMercedes\FlexFields\Models\Entity;

/**
 * The structure of an entity changed: the entity itself, one of its fields or
 * one of its field groups was created, updated or deleted. Bulk operations
 * (Flex::sync, imports) dispatch it once per change, so listeners doing
 * expensive work should debounce it (e.g. a unique queued job).
 */
class EntitySchemaChanged
{
    use Dispatchable;

    public readonly int $entityId;

    public readonly string $entitySlug;

    public readonly ?int $tenantId;

    public function __construct(public readonly Entity $entity, public readonly bool $deleted = false)
    {
        $this->entityId = (int) $entity->getKey();
        $this->entitySlug = (string) $entity->slug;
        $this->tenantId = $entity->tenant_id !== null ? (int) $entity->tenant_id : null;
    }
}
