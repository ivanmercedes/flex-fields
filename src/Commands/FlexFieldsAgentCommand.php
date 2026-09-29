<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Commands;

use Illuminate\Console\Command;
use IvanMercedes\FlexFields\Ai\Agents\FlexFieldsAssistant;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Tools\ToolNameResolver;
use Throwable;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class FlexFieldsAgentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flex:agent
                            {prompt? : The message or instruction for the agent}
                            {--tenant= : The workspace/tenant ID}
                            {--provider= : AI provider override (e.g. openai, anthropic, gemini, ollama)}
                            {--model= : Model name override}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interact directly with the FlexFields AI Assistant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! interface_exists(Agent::class)) {
            $this->components->error('The [laravel/ai] package is required to use the FlexFields AI Assistant.');
            $this->line('Please run: <comment>composer require laravel/ai</comment>');

            return Command::FAILURE;
        }

        $tenantId = $this->resolveTenantId();
        $tenantName = $this->resolveTenantName($tenantId);

        if ($tenantId !== null) {
            $label = $tenantName ? "[{$tenantId}] {$tenantName}" : "[{$tenantId}]";
            $this->components->info("FlexFields AI Assistant initialized for Tenant: {$label}");
        } else {
            $this->components->info('FlexFields AI Assistant initialized (Global / Single Tenant)');
        }

        $agent = new FlexFieldsAssistant(tenantId: $tenantId);
        $tools = iterator_to_array($agent->tools());

        $toolNames = array_map(function ($tool) {
            if (class_exists(ToolNameResolver::class)) {
                return ToolNameResolver::resolve($tool);
            }

            return is_object($tool) ? class_basename($tool) : (string) $tool;
        }, $tools);

        $this->components->twoColumnDetail('Loaded Tools (' . count($toolNames) . ')', implode(', ', $toolNames));

        $prompt = $this->argument('prompt');

        if ($prompt) {
            $this->executePrompt($agent, (string) $prompt);

            return Command::SUCCESS;
        }

        info('Type your message to test FlexFields AI (or type "exit" to quit):');

        while (true) {
            $input = text(
                label: 'Prompt',
                placeholder: 'e.g. List all entities, or show schema for products...',
                required: true,
            );

            if (in_array(trim(strtolower($input)), ['exit', 'quit', 'q'], true)) {
                $this->info('Goodbye!');

                break;
            }

            $this->executePrompt($agent, $input);
        }

        return Command::SUCCESS;
    }

    /**
     * Resolve the target tenant ID from option, config, or first tenant instance.
     */
    protected function resolveTenantId(): ?int
    {
        $optionTenant = $this->option('tenant');
        if ($optionTenant !== null && is_numeric($optionTenant)) {
            return (int) $optionTenant;
        }

        if (config('flex-fields.tenancy.enabled', false)) {
            $tenantModel = config('flex-fields.tenancy.tenant_model');
            if ($tenantModel && class_exists($tenantModel)) {
                try {
                    $first = $tenantModel::first();

                    return $first ? (int) $first->getKey() : null;
                } catch (Throwable) {
                }
            }
        }

        return null;
    }

    /**
     * Resolve human-readable tenant name if available.
     */
    protected function resolveTenantName(?int $tenantId): ?string
    {
        if ($tenantId === null) {
            return null;
        }

        $tenantModel = config('flex-fields.tenancy.tenant_model');
        if ($tenantModel && class_exists($tenantModel)) {
            try {
                $tenant = $tenantModel::find($tenantId);

                return $tenant?->name ?? (string) $tenant?->getKey();
            } catch (Throwable) {
            }
        }

        return null;
    }

    /**
     * Execute a prompt using the agent.
     */
    protected function executePrompt(FlexFieldsAssistant $agent, string $prompt): void
    {
        $provider = $this->option('provider');
        $model = $this->option('model');

        try {
            $response = spin(
                fn () => $agent->prompt($prompt, provider: $provider, model: $model),
                message: 'Executing FlexFields Agent...'
            );

            note((string) $response);
        } catch (Throwable $e) {
            $this->components->error("Execution error: {$e->getMessage()}");
            $this->line('Detail: ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
