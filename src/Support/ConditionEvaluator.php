<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use InvalidArgumentException;

/**
 * Evaluates "show when" visibility rules stored in a field's settings:
 *
 *   'show_when' => [
 *       'match' => 'all',            // all | any
 *       'conditions' => [
 *           ['field' => 'type', 'operator' => 'equals', 'value' => 'digital'],
 *       ],
 *   ]
 *
 * The same evaluator is used by the Filament form, the schema introspection
 * exposed to AI agents and any headless consumer.
 */
class ConditionEvaluator
{
    public const OPERATORS = [
        'equals',
        'not_equals',
        'in',
        'not_in',
        'contains',
        'filled',
        'blank',
        'gt',
        'gte',
        'lt',
        'lte',
        'is_true',
        'is_false',
    ];

    /** Operators that do not compare against a value. */
    public const VALUELESS = ['filled', 'blank', 'is_true', 'is_false'];

    /**
     * Normalize a show_when definition. Accepts the full structure or a bare
     * list of conditions. Returns null when there is nothing to evaluate.
     *
     * @return array{match: string, conditions: array<int, array{field: string, operator: string, value: mixed}>}|null
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(mixed $showWhen): ?array
    {
        if (empty($showWhen) || ! is_array($showWhen)) {
            return null;
        }

        $conditions = array_is_list($showWhen) ? $showWhen : ($showWhen['conditions'] ?? []);
        $match = array_is_list($showWhen) ? 'all' : strtolower((string) ($showWhen['match'] ?? 'all'));

        if (! in_array($match, ['all', 'any'], true)) {
            throw new InvalidArgumentException("Invalid show_when match [{$match}]. Use 'all' or 'any'.");
        }

        $normalized = [];

        foreach ((array) $conditions as $condition) {
            if (! is_array($condition) || empty($condition['field'])) {
                continue;
            }

            $operator = (string) ($condition['operator'] ?? 'equals');

            if (! in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException("Invalid show_when operator [{$operator}]. Supported: " . implode(', ', self::OPERATORS));
            }

            $normalized[] = [
                'field' => (string) $condition['field'],
                'operator' => $operator,
                'value' => in_array($operator, self::VALUELESS, true) ? null : ($condition['value'] ?? null),
            ];
        }

        return $normalized === [] ? null : ['match' => $match, 'conditions' => $normalized];
    }

    /**
     * Determine whether the rules pass for the given values.
     *
     * @param  callable(string): mixed|array<string, mixed>  $values  field key => value, or a resolver
     */
    public static function passes(mixed $showWhen, callable | array $values): bool
    {
        try {
            $rules = self::normalize($showWhen);
        } catch (InvalidArgumentException) {
            return true;
        }

        if ($rules === null) {
            return true;
        }

        $resolve = is_callable($values) ? $values : fn (string $key) => $values[$key] ?? null;

        $results = array_map(
            fn (array $condition) => self::check($condition['operator'], $resolve($condition['field']), $condition['value']),
            $rules['conditions'],
        );

        return $rules['match'] === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }

    /**
     * Keys of the fields that control the visibility of others.
     *
     * @return array<int, string>
     */
    public static function controllingKeys(mixed $showWhen): array
    {
        try {
            $rules = self::normalize($showWhen);
        } catch (InvalidArgumentException) {
            return [];
        }

        return $rules === null ? [] : array_values(array_unique(array_column($rules['conditions'], 'field')));
    }

    public static function check(string $operator, mixed $actual, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => self::equals($actual, $expected),
            'not_equals' => ! self::equals($actual, $expected),
            'in' => self::in($actual, $expected),
            'not_in' => ! self::in($actual, $expected),
            'contains' => self::contains($actual, $expected),
            'filled' => ! self::isBlank($actual),
            'blank' => self::isBlank($actual),
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'is_true' => filter_var($actual, FILTER_VALIDATE_BOOLEAN) === true,
            'is_false' => filter_var($actual, FILTER_VALIDATE_BOOLEAN) === false,
            default => true,
        };
    }

    protected static function equals(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array(self::scalar($expected), array_map(self::scalar(...), $actual), true);
        }

        if (is_bool($actual) || is_bool($expected)) {
            return filter_var($actual, FILTER_VALIDATE_BOOLEAN) === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        return self::scalar($actual) === self::scalar($expected);
    }

    protected static function in(mixed $actual, mixed $expected): bool
    {
        $options = is_array($expected)
            ? array_map(self::scalar(...), $expected)
            : array_map('trim', explode(',', (string) $expected));

        foreach ((array) $actual as $value) {
            if (in_array(self::scalar($value), $options, true)) {
                return true;
            }
        }

        return false;
    }

    protected static function contains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array(self::scalar($expected), array_map(self::scalar(...), $actual), true);
        }

        return $expected !== null && $expected !== '' && str_contains((string) $actual, (string) $expected);
    }

    protected static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === false;
    }

    protected static function scalar(mixed $value): string
    {
        return is_scalar($value) || $value === null ? trim((string) $value) : (string) json_encode($value);
    }
}
