<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai;

use Stringable;

/**
 * Outcome of a FlexFields AI tool. The Laravel AI SDK receives its text
 * through handle(); adapters (MCP servers, HTTP endpoints) call run() to know
 * whether the operation failed without parsing that text.
 */
class ToolResult implements Stringable
{
    public function __construct(
        public readonly string $content,
        public readonly bool $isError = false,
    ) {}

    public function __toString(): string
    {
        return $this->content;
    }

    public static function success(string $content): self
    {
        return new self($content);
    }

    public static function error(string $message): self
    {
        return new self($message, isError: true);
    }
}
