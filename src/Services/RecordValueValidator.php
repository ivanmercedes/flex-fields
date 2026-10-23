<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\ValidationRules;

/**
 * Validates custom field values against the entity schema (types, options,
 * required flags, relations, translations and the field's own rules).
 *
 * Used by every non-Filament entry point (AI tools, REST API, imports) so
 * that the same constraints apply everywhere.
 */
class RecordValueValidator
{
    /**
     * Validate and normalize values. Unknown keys are dropped.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Entity $entity, array $values, bool $creating = false): array
    {
        $fields = $entity->customFields()->where('is_active', true)->get()->keyBy('key');

        $values = $this->normalize($fields, $values);

        $rules = [];
        $attributes = [];

        foreach ($fields as $key => $field) {
            if (! $creating && ! array_key_exists($key, $values)) {
                continue;
            }

            foreach ($this->rulesFor($entity, $field, $creating) as $path => $fieldRules) {
                $rules[$path] = $fieldRules;
                $attributes[$path] = $field->label;
            }
        }

        $validator = Validator::make($values, $rules, [], $attributes);
        $validator->validate();

        return array_intersect_key($values, $fields->all());
    }

    /**
     * Same as validate() but returns error messages instead of throwing.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array<int, string>>
     */
    public function errors(Entity $entity, array $values, bool $creating = false): array
    {
        try {
            $this->validate($entity, $values, $creating);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }

    /**
     * Build validation rules for a single field, keyed by attribute path.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rulesFor(Entity $entity, CustomField $field, bool $creating = true): array
    {
        $key = $field->key;
        $presence = $field->is_required
            ? ($creating ? ['required'] : ['sometimes', 'required'])
            : ['nullable'];

        if (FieldTypes::isTranslatable($field)) {
            $locales = FieldTypes::locales();
            $rules = [$key => [...$presence, 'array']];

            foreach ($locales as $locale) {
                $localeRules = ['nullable', 'string'];

                if ($field->is_required && $locale === FieldTypes::fallbackLocale()) {
                    $localeRules = ['required_with:' . $key, 'string'];
                }

                $rules["{$key}.{$locale}"] = [...$localeRules, ...ValidationRules::forField($field->validation_rules)];
            }

            return $rules;
        }

        $custom = ValidationRules::forField($field->validation_rules);
        $multiple = FieldTypes::isMultiple($field);

        return match ($field->type) {
            'number' => [$key => [...$presence, 'numeric', ...$custom]],
            'email' => [$key => [...$presence, 'string', 'email', ...$custom]],
            'url' => [$key => [...$presence, 'string', 'url', ...$custom]],
            'date', 'datetime' => [$key => [...$presence, 'date', ...$custom]],
            'boolean' => [$key => [...$presence, 'boolean']],
            'text' => [$key => [...$presence, 'string', 'max:255', ...$custom]],
            'phone' => [$key => [...$presence, 'string', 'max:50', 'regex:/^[0-9+\-\s().#*xX]*$/', ...$custom]],
            'color' => [$key => [...$presence, 'string', 'max:64']],
            'select' => [$key => [...$presence, ...$this->optionRule($field), ...$custom]],
            'multiselect' => [
                $key => [...$presence, 'array', ...$custom],
                "{$key}.*" => $this->optionRule($field),
            ],
            'tags' => [
                $key => [...$presence, 'array', ...$custom],
                "{$key}.*" => ['string'],
            ],
            'repeater', 'json' => [$key => [...$presence, 'array', ...$custom]],
            'address' => array_merge(
                [$key => [...$presence, 'array']],
                collect($this->addressParts($field))
                    ->mapWithKeys(fn (string $part) => ["{$key}.{$part}" => ['nullable', 'string', 'max:255']])
                    ->all(),
            ),
            'link' => [
                $key => [...$presence, 'array'],
                // Relative URLs ("/contact", "#pricing") are valid links.
                "{$key}.url" => [$field->is_required ? 'required' : 'nullable', 'string', 'max:2048'],
                "{$key}.title" => ['nullable', 'string', 'max:255'],
                "{$key}.target" => ['nullable', 'in:' . implode(',', FieldTypes::LINK_TARGETS)],
            ],
            'relationship' => $multiple
                ? [
                    $key => [...$presence, 'array', $this->relationshipRule($entity, $field)],
                    "{$key}.*" => ['integer'],
                ]
                : [$key => [...$presence, 'integer', $this->relationshipRule($entity, $field)]],
            'image', 'file' => $multiple
                ? [$key => [...$presence, 'array'], "{$key}.*" => ['string']]
                : [$key => [...$presence, 'string']],
            default => [$key => [...$presence, ...$custom]],
        };
    }

    /**
     * Decode JSON strings and coerce scalar formats before validation.
     *
     * @param  Collection<string, CustomField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function normalize(Collection $fields, array $values): array
    {
        foreach ($values as $key => $value) {
            $field = $fields->get($key);

            if (! $field) {
                continue;
            }

            $structured = FieldTypes::isTranslatable($field)
                || in_array($field->type, FieldTypes::STRUCTURED, true)
                || (in_array($field->type, ['relationship', 'image', 'file'], true) && FieldTypes::isMultiple($field));

            if ($structured && is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                $values[$key] = is_array($decoded) ? $decoded : $value;
            }

            if ($field->type === 'address' && is_array($values[$key])) {
                $values[$key] = array_intersect_key($values[$key], array_flip($this->addressParts($field)));
            }

            if ($field->type === 'link' && is_array($values[$key])) {
                $values[$key] = array_intersect_key($values[$key], array_flip(FieldTypes::LINK_PARTS));
            }

            if ($field->type === 'relationship' && ! FieldTypes::isMultiple($field) && is_numeric($value)) {
                $values[$key] = (int) $value;
            }

            if ($value === '') {
                $values[$key] = null;
            }
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    public function addressParts(CustomField $field): array
    {
        $parts = array_values(array_intersect((array) ($field->settings['address_parts'] ?? []), FieldTypes::ADDRESS_PARTS));

        return $parts ?: FieldTypes::ADDRESS_PARTS;
    }

    /**
     * @return array<int, mixed>
     */
    protected function optionRule(CustomField $field): array
    {
        $options = array_map('strval', array_keys($field->parsed_options));

        return empty($options) ? [] : [Rule::in($options)];
    }

    /**
     * Ensure every referenced record belongs to the related entity of the same tenant.
     */
    protected function relationshipRule(Entity $entity, CustomField $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($entity, $field) {
            $ids = array_values(array_unique(array_map('intval', array_filter((array) $value, 'is_numeric'))));

            if (count($ids) !== count(array_filter((array) $value, fn ($v) => $v !== null && $v !== ''))) {
                $fail("The {$field->label} field must contain record IDs.");

                return;
            }

            if ($ids === []) {
                return;
            }

            $related = Entity::query()
                ->where('slug', $field->settings['related_entity'] ?? null)
                ->where(fn ($q) => $entity->tenant_id === null
                    ? $q->whereNull('tenant_id')
                    : $q->where('tenant_id', $entity->tenant_id))
                ->first();

            if (! $related) {
                $fail("The {$field->label} field points to an entity that does not exist.");

                return;
            }

            $found = $related->records()->whereIn('id', $ids)->count();

            if ($found !== count($ids)) {
                $fail("The {$field->label} field references records that do not exist in [{$related->slug}].");
            }
        };
    }
}
