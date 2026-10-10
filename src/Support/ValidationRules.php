<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use InvalidArgumentException;

/**
 * Whitelisted validation rules offered by the visual validation builder.
 *
 * Rules are stored on custom fields as plain Laravel rule strings
 * (e.g. ['min:3', 'max:100', 'regex:/^[A-Z]/']) so they stay compatible with
 * the schema builder and any existing data.
 */
class ValidationRules
{
    /**
     * Rule name => whether it requires a parameter.
     *
     * @var array<string, bool>
     */
    public const RULES = [
        'min' => true,
        'max' => true,
        'between' => true,
        'size' => true,
        'digits' => true,
        'digits_between' => true,
        'regex' => true,
        'not_regex' => true,
        'starts_with' => true,
        'ends_with' => true,
        'in' => true,
        'not_in' => true,
        'date_format' => true,
        'after' => true,
        'after_or_equal' => true,
        'before' => true,
        'before_or_equal' => true,
        'multiple_of' => true,
        'alpha' => false,
        'alpha_num' => false,
        'alpha_dash' => false,
        'ascii' => false,
        'lowercase' => false,
        'uppercase' => false,
        'integer' => false,
        'numeric' => false,
        'email' => false,
        'url' => false,
        'uuid' => false,
        'ulid' => false,
        'ip' => false,
        'json' => false,
        'distinct' => false,
    ];

    /**
     * Options for the rule select of the visual builder.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (array_keys(self::RULES) as $rule) {
            $options[$rule] = Label::trans("flex-fields::flex-fields.validation_rules.{$rule}", default: $rule);
        }

        return $options;
    }

    public static function needsValue(?string $rule): bool
    {
        return $rule !== null && (self::RULES[$rule] ?? false);
    }

    /**
     * Convert stored rule strings into repeater items for the visual builder.
     *
     * @param  array<int, mixed>|null  $rules
     * @return array<int, array{rule: string, value: ?string}>
     */
    public static function toBuilderState(?array $rules): array
    {
        $items = [];

        foreach ((array) $rules as $rule) {
            if (! is_string($rule) || $rule === '' || $rule === 'required') {
                continue;
            }

            [$name, $value] = array_pad(explode(':', $rule, 2), 2, null);
            $items[] = ['rule' => $name, 'value' => $value];
        }

        return $items;
    }

    /**
     * Convert repeater items from the visual builder into rule strings.
     *
     * @param  array<int|string, mixed>|null  $items
     * @return array<int, string>
     */
    public static function fromBuilderState(?array $items): array
    {
        $rules = [];

        foreach ((array) $items as $item) {
            $name = is_array($item) ? trim((string) ($item['rule'] ?? '')) : '';

            if ($name === '') {
                continue;
            }

            $value = trim((string) ($item['value'] ?? ''));
            $rules[] = self::needsValue($name) && $value !== '' ? "{$name}:{$value}" : $name;
        }

        return array_values(array_unique($rules));
    }

    /**
     * Validate rules coming from untrusted sources (AI agents, API, imports).
     *
     * @param  array<int, mixed>|null  $rules
     * @return array<int, string>
     *
     * @throws InvalidArgumentException
     */
    public static function sanitize(?array $rules): array
    {
        $clean = [];

        foreach ((array) $rules as $rule) {
            if (! is_string($rule) || trim($rule) === '') {
                continue;
            }

            $rule = trim($rule);
            [$name, $value] = array_pad(explode(':', $rule, 2), 2, null);

            if ($name === 'required' || $name === 'nullable') {
                continue;
            }

            if (! array_key_exists($name, self::RULES)) {
                throw new InvalidArgumentException("Validation rule [{$name}] is not allowed. Allowed rules: " . implode(', ', array_keys(self::RULES)));
            }

            if (self::RULES[$name] && ($value === null || $value === '')) {
                throw new InvalidArgumentException("Validation rule [{$name}] requires a parameter (e.g. {$name}:value).");
            }

            if (in_array($name, ['regex', 'not_regex'], true) && @preg_match((string) $value, '') === false) {
                throw new InvalidArgumentException("Validation rule [{$rule}] contains an invalid regular expression.");
            }

            $clean[] = $rule;
        }

        return array_values(array_unique($clean));
    }

    /**
     * Rules to apply on form components / validators.
     *
     * Presence rules are skipped (presence is controlled by the field's
     * "is_required" flag) and so is any rule outside the whitelist: rules
     * stored by older versions (typos, "exists:", custom strings...) are
     * ignored instead of breaking forms with a validator exception.
     *
     * @param  array<int, mixed>|null  $rules
     * @return array<int, string>
     */
    public static function forField(?array $rules): array
    {
        return array_values(array_filter((array) $rules, function ($rule) {
            if (! is_string($rule) || trim($rule) === '') {
                return false;
            }

            [$name, $value] = array_pad(explode(':', trim($rule), 2), 2, null);

            if (! array_key_exists($name, self::RULES) || (self::RULES[$name] && ($value === null || $value === ''))) {
                return false;
            }

            return ! in_array($name, ['regex', 'not_regex'], true) || @preg_match((string) $value, '') !== false;
        }));
    }
}
