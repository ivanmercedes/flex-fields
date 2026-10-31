<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\FieldValue;

/**
 * Filters and sorts an entity's records by their field values (EAV).
 *
 * Filters: ['price' => ['gte' => 10], 'kind' => 'physical', 'tags' => ['contains' => 'red']]
 * (a scalar means "eq"). Operators: eq, ne, gt, gte, lt, lte, in (array or
 * comma separated), contains and null (true / false).
 *
 * Sort: "-price,title" — field keys or record columns, "-" for descending.
 */
class RecordFilters
{
    public const OPERATORS = ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'in', 'contains', 'null'];

    /** Record columns that can be sorted by. */
    public const SORTABLE_COLUMNS = ['id', 'title', 'slug', 'order', 'status', 'publish_at', 'created_at', 'updated_at'];

    /** Types whose values can be compared (the others only support "null"). */
    public const COMPARABLE_TYPES = [
        'text', 'textarea', 'number', 'email', 'url', 'date', 'datetime', 'boolean', 'select',
        'multiselect', 'tags', 'color', 'phone', 'relationship',
    ];

    /** Types stored as a JSON list, matched by element with "eq" / "contains". */
    protected const LIST_TYPES = ['multiselect', 'tags'];

    /**
     * @param  array<string, mixed>  $filters
     *
     * @throws ValidationException
     */
    public static function apply(HasMany | Builder $query, Entity $entity, array $filters): void
    {
        $fields = static::fields($entity);

        foreach ($filters as $key => $conditions) {
            $field = $fields[$key] ?? throw ValidationException::withMessages([
                "filter.{$key}" => "Unknown field [{$key}].",
            ]);

            foreach (is_array($conditions) && ! array_is_list($conditions) ? $conditions : ['eq' => $conditions] as $operator => $value) {
                static::where($query, $field, (string) $operator, $value);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public static function sort(HasMany | Builder $query, Entity $entity, string $sort): void
    {
        $fields = static::fields($entity);

        foreach (array_filter(array_map('trim', explode(',', $sort))) as $column) {
            $direction = str_starts_with($column, '-') ? 'desc' : 'asc';
            $column = ltrim($column, '-+');

            if (in_array($column, self::SORTABLE_COLUMNS, true)) {
                $query->orderBy($query->getModel()->qualifyColumn($column), $direction);

                continue;
            }

            $field = $fields[$column] ?? null;

            if ($field === null || ! in_array($field->type, self::COMPARABLE_TYPES, true) || FieldTypes::isTranslatable($field)) {
                throw ValidationException::withMessages(['sort' => "Records cannot be sorted by [{$column}]."]);
            }

            $query->orderBy(
                FieldValue::query()
                    ->select(static::valueColumn($field))
                    ->whereColumn('ff_field_values.entity_record_id', $query->getModel()->qualifyColumn('id'))
                    ->where('ff_field_values.custom_field_id', $field->id)
                    ->limit(1),
                $direction,
            );
        }
    }

    /**
     * @return array<string, CustomField>
     */
    protected static function fields(Entity $entity): array
    {
        return FieldCache::getForEntity($entity)->keyBy('key')->all();
    }

    protected static function where(HasMany | Builder $query, CustomField $field, string $operator, mixed $value): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(["filter.{$field->key}" => $message]);

        if (! in_array($operator, self::OPERATORS, true)) {
            $fail("Unknown operator [{$operator}]. Allowed: " . implode(', ', self::OPERATORS) . '.');
        }

        if ($operator === 'null') {
            $isNull = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $hasValue = fn (Builder $q) => $q->where('custom_field_id', $field->id)->whereNotNull('value')->whereNotIn('value', ['', '[]', '{}']);

            $isNull ? $query->whereDoesntHave('fieldValues', $hasValue) : $query->whereHas('fieldValues', $hasValue);

            return;
        }

        if (! in_array($field->type, self::COMPARABLE_TYPES, true)) {
            $fail("Field [{$field->key}] of type [{$field->type}] only supports the [null] operator.");
        }

        if (is_array($value) && $operator !== 'in') {
            $fail("The [{$operator}] operator expects a single value.");
        }

        if ($field->type === 'boolean' && in_array($operator, ['eq', 'ne'], true)) {
            $isTrue = filter_var($value, FILTER_VALIDATE_BOOLEAN) === ($operator === 'eq');
            $truthy = fn (Builder $q) => $q->where('custom_field_id', $field->id)->whereIn('value', ['1', 'true']);

            $isTrue ? $query->whereHas('fieldValues', $truthy) : $query->whereDoesntHave('fieldValues', $truthy);

            return;
        }

        if (FieldTypes::isTranslatable($field) && $operator !== 'contains') {
            $fail("Translatable field [{$field->key}] only supports the [contains] and [null] operators.");
        }

        if ($operator === 'ne') {
            $query->whereDoesntHave('fieldValues', fn (Builder $q) => static::compare($q->where('custom_field_id', $field->id), $field, 'eq', $value));

            return;
        }

        $query->whereHas('fieldValues', fn (Builder $q) => static::compare($q->where('custom_field_id', $field->id), $field, $operator, $value));
    }

    protected static function compare(Builder $query, CustomField $field, string $operator, mixed $value): void
    {
        if ($operator === 'in') {
            $values = is_array($value) ? $value : explode(',', (string) $value);
            $query->where(function (Builder $q) use ($field, $values) {
                foreach ($values as $item) {
                    $q->orWhere(fn (Builder $q) => static::compare($q, $field, 'eq', trim((string) $item)));
                }
            });

            return;
        }

        if (in_array($field->type, self::LIST_TYPES, true) && in_array($operator, ['eq', 'contains'], true)) {
            $query->where('value', 'like', '%' . json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '%');

            return;
        }

        if ($field->type === 'relationship' && in_array($operator, ['eq', 'contains'], true)) {
            static::whereContainsId($query, (int) $value);

            return;
        }

        if ($operator === 'contains') {
            $query->where('value', 'like', '%' . $value . '%');

            return;
        }

        $comparison = ['eq' => '=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='][$operator];

        if ($field->type === 'number') {
            if (! is_numeric($value)) {
                throw ValidationException::withMessages(["filter.{$field->key}" => "Field [{$field->key}] expects a number."]);
            }

            $query->where(static::valueColumn($field), $comparison, (float) $value);

            return;
        }

        $query->where('value', $comparison, (string) $value);
    }

    /**
     * Relationship values are stored as an ID or a JSON list of IDs.
     */
    protected static function whereContainsId(Builder $query, int $id): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('value', (string) $id)
            ->orWhere('value', "[{$id}]")
            ->orWhere('value', 'like', "[{$id},%")
            ->orWhere('value', 'like', "%,{$id},%")
            ->orWhere('value', 'like', "%,{$id}]")
            ->orWhere('value', 'like', "%\"{$id}\"%"));
    }

    /**
     * The value column, cast to a number for number fields so 10 > 9.
     */
    protected static function valueColumn(CustomField $field): Expression | string
    {
        if ($field->type !== 'number') {
            return 'ff_field_values.value';
        }

        return DB::raw(match (FieldValue::query()->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => 'CAST(ff_field_values.value AS DECIMAL(65, 10))',
            'pgsql' => "CAST(NULLIF(ff_field_values.value, '') AS NUMERIC)",
            'sqlsrv' => 'TRY_CAST(ff_field_values.value AS FLOAT)',
            default => 'CAST(ff_field_values.value AS REAL)',
        });
    }
}
