<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Resources\CustomFieldResource\Concerns;

use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\FieldDomainService;
use IvanMercedes\FlexFields\Support\ValidationRules;
use RuntimeException;

/**
 * Maps the visual builders of the custom field form (validation rules and
 * conditional visibility) to the stored structure, and normalizes settings
 * through the domain layer.
 */
trait PreparesFieldData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillBuilders(array $data): array
    {
        $data['validation_builder'] = ValidationRules::toBuilderState($data['validation_rules'] ?? []);

        $showWhen = $data['settings']['show_when'] ?? null;
        $data['show_when_match'] = $showWhen['match'] ?? 'all';
        $data['show_when_conditions'] = $showWhen['conditions'] ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applyBuilders(array $data): array
    {
        if (array_key_exists('validation_builder', $data)) {
            $data['validation_rules'] = ValidationRules::fromBuilderState($data['validation_builder']);
        }

        $settings = (array) ($data['settings'] ?? []);

        if (array_key_exists('show_when_conditions', $data)) {
            $conditions = array_values((array) $data['show_when_conditions']);
            $settings['show_when'] = $conditions === []
                ? null
                : ['match' => $data['show_when_match'] ?? 'all', 'conditions' => $conditions];
        }

        unset($data['validation_builder'], $data['show_when_match'], $data['show_when_conditions']);

        $entity = Entity::find($data['entity_id'] ?? $this->record?->entity_id ?? null);

        if ($entity) {
            try {
                $data['settings'] = app(FieldDomainService::class)->normalizeSettings(
                    $entity,
                    (string) ($data['type'] ?? 'text'),
                    $settings,
                    $data['key'] ?? null,
                );
            } catch (RuntimeException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();

                throw new Halt;
            }
        } else {
            $data['settings'] = $settings;
        }

        return $data;
    }
}
