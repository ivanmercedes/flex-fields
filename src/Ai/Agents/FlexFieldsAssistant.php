<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Ai\Agents;

use Filament\Facades\Filament;
use IvanMercedes\FlexFields\Ai\FlexFieldsAi;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

class FlexFieldsAssistant implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @var array<Message>
     */
    protected array $messages = [];

    /**
     * @param  array<string>  $categories
     */
    public function __construct(
        public ?int $tenantId = null,
        public array $categories = ['read', 'content', 'publishing', 'structure'],
        public bool $requireApprovals = false,
    ) {
        if ($this->tenantId === null && config('flex-fields.tenancy.enabled', false)) {
            $this->tenantId = $this->resolveTenantId();
        }
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable | string
    {
        return <<<'PROMPT'
You are the FlexFields AI Assistant, an intelligent manager for dynamic entities, custom fields, taxonomies, and content records.

Your capabilities:
1. Read & Introspection: List entities, introspect entity schemas, list fields, and view records.
2. Content Operations: Create, update, and manage records along with custom field values.
3. Publishing: Publish and unpublish content entries.
4. Structure: Create and modify entities, custom fields, and taxonomies (categories).

Guidelines:
- When asked to create or update content, always check the entity schema using GetEntitySchema first to know valid field keys, data types, and validation rules.
- Format results clearly with entity names, record titles, and affected field values.
- Respect multi-tenancy boundaries and tenant context at all times.
PROMPT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * Set the conversation messages.
     *
     * @param  array<Message>  $messages
     */
    public function withMessages(array $messages): static
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        $builder = FlexFieldsAi::configure()
            ->forTenant($this->tenantId)
            ->categories($this->categories);

        if (! $this->requireApprovals) {
            $builder->withoutApprovals();
        }

        return $builder->tools();
    }

    /**
     * Dynamically resolve the active tenant ID from Filament or the configured tenant model.
     */
    protected function resolveTenantId(): ?int
    {
        try {
            if (class_exists(Filament::class) && Filament::hasTenancy() && $tenant = Filament::getTenant()) {
                return (int) $tenant->getKey();
            }
        } catch (Throwable) {
        }

        $tenantModel = config('flex-fields.tenancy.tenant_model');
        if ($tenantModel && class_exists($tenantModel)) {
            try {
                $first = $tenantModel::first();

                return $first ? (int) $first->getKey() : null;
            } catch (Throwable) {
            }
        }

        return null;
    }
}
