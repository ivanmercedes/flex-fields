<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai;

enum AiToolCategory: string
{
    case Read = 'read';
    case Structure = 'structure';
    case Content = 'content';
    case Publishing = 'publishing';
    case Destructive = 'destructive';

    /**
     * Get human-readable description for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Read => 'Read-only tools for inspecting entities, schemas, fields, and records',
            self::Structure => 'Structural tools for managing entities, fields, and categories',
            self::Content => 'Content management tools for records and custom field values',
            self::Publishing => 'Publishing tools for publishing and unpublishing records',
            self::Destructive => 'Destructive operations (delete entity, delete field, etc.) requiring extra care',
        };
    }
}
