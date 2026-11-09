<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Ai\AiToolCategory;
use Laravel\Ai\Contracts\Tool as AiTool;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Exposes a FlexFields AI tool through a laravel/mcp server.
 *
 * Both SDKs describe their input with Illuminate's JsonSchema, so the schema,
 * validation, tenancy and permission checks of the wrapped tool are reused
 * as-is. Build the catalog with FlexFieldsAi::configure()->...->mcpTools().
 */
class FlexFieldsMcpTool extends Tool
{
    /**
     * @param  AiTool  $tool  A FlexFields AI tool (uses InteractsWithFlexDomain)
     */
    public function __construct(protected AiTool $tool)
    {
        $this->name = $tool->toolName();
        $this->title = $tool->toolTitle();
        $this->description = (string) $tool->description();
    }

    public function wrapped(): AiTool
    {
        return $this->tool;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->tool->schema($schema);
    }

    public function handle(Request $request): Response
    {
        try {
            $result = $this->tool->run(new AiRequest($request->all()));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()) ?: $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return Response::error($exception instanceof QueryException
                ? 'The tool failed because of a database error.'
                : 'The tool failed: ' . $exception->getMessage());
        }

        return $result->isError ? Response::error($result->content) : Response::text($result->content);
    }

    /**
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        $categories = $this->tool->toolCategory();
        $categories = array_map(
            fn (AiToolCategory $category) => $category->value,
            is_array($categories) ? $categories : [$categories],
        );

        return [
            'readOnlyHint' => $categories === [AiToolCategory::Read->value],
            'destructiveHint' => in_array(AiToolCategory::Destructive->value, $categories, true),
            'openWorldHint' => false,
        ];
    }
}
