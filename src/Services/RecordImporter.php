<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Support\FieldTypes;
use IvanMercedes\FlexFields\Support\MediaLibrary;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;

/**
 * Bulk import of records from CSV or Excel (.xlsx) files with a preview step.
 *
 * Flow: read() → preview() (validation, nothing written) → import().
 */
class RecordImporter
{
    public const MODE_CREATE = 'create';

    public const MODE_UPSERT = 'upsert';

    /** Record attributes that can be imported besides custom fields. */
    public const ATTRIBUTE_COLUMNS = ['id', 'title', 'slug', 'status', 'order', 'categories'];

    public function __construct(
        protected RecordDomainService $records = new RecordDomainService,
        protected RecordValueValidator $validator = new RecordValueValidator,
    ) {}

    /**
     * Header row for the downloadable template.
     *
     * @return array<int, string>
     */
    public function templateColumns(Entity $entity): array
    {
        return [
            'title',
            'slug',
            'status',
            'categories',
            ...$entity->customFields()->where('is_active', true)->get()
                ->reject(fn (CustomField $field) => MediaLibrary::handles($field))
                ->pluck('key')
                ->all(),
        ];
    }

    public function templateCsv(Entity $entity): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $this->templateColumns($entity), (string) config('flex-fields.import_export.csv_delimiter', ','), escape: '');
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Read a CSV or XLSX file into a list of associative rows (header => value).
     *
     * @return array<int, array<string, string|null>>
     */
    public function read(string $path, ?string $extension = null): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Import file [{$path}] is not readable.");
        }

        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        $rows = match ($extension) {
            'xlsx' => $this->readXlsx($path),
            'csv', 'txt', '' => $this->readCsv($path),
            default => throw new RuntimeException("Unsupported import format [{$extension}]. Use .csv or .xlsx."),
        };

        $maxRows = (int) config('flex-fields.import_export.max_rows', 5000);
        if ($maxRows > 0 && count($rows) > $maxRows) {
            throw new RuntimeException('The file contains ' . count($rows) . " rows; the maximum allowed is {$maxRows}.");
        }

        return $rows;
    }

    /**
     * Parse a CSV string (useful for tests and API uploads).
     *
     * @return array<int, array<string, string|null>>
     */
    public function parseCsvString(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $rows = $this->readCsvHandle($handle);
        fclose($handle);

        return $rows;
    }

    /**
     * Validate rows without writing anything.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{columns: array{mapped: array<string, string>, unknown: array<int, string>}, rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function preview(Entity $entity, array $rows, string $mode = self::MODE_CREATE): array
    {
        $this->ensureValidMode($mode);

        $fields = $entity->customFields()->where('is_active', true)->get();
        $headers = array_keys($rows[0] ?? []);
        $mapping = $this->mapColumns($headers, $fields);

        $previewRows = [];
        $summary = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'create' => 0, 'update' => 0];
        $seenSlugs = [];

        foreach ($rows as $index => $row) {
            $parsed = $this->parseRow($row, $mapping, $fields);
            $existing = $mode === self::MODE_UPSERT ? $this->findExisting($entity, $parsed['attributes']) : null;
            $action = $existing ? 'update' : 'create';
            $errors = [];

            $status = $parsed['attributes']['status'] ?? null;
            if ($status !== null && ! in_array($status, RecordDomainService::STATUSES, true)) {
                $errors['status'][] = "Invalid status [{$status}]. Allowed: " . implode(', ', RecordDomainService::STATUSES) . '.';
            }

            try {
                $this->records->resolveCategoryIds($entity, $parsed['categories'] ?? []);
            } catch (RuntimeException $e) {
                $errors['categories'][] = $e->getMessage();
            }

            foreach ($this->validator->errors($entity, $parsed['values'], creating: $action === 'create') as $key => $messages) {
                $errors[$key] = $messages;
            }

            $slug = $parsed['attributes']['slug'] ?? null;
            if ($slug !== null && $action === 'create') {
                if (in_array($slug, $seenSlugs, true)) {
                    $errors['slug'][] = "Duplicate slug [{$slug}] in the file.";
                }
                $seenSlugs[] = $slug;
            }

            $valid = $errors === [];

            $previewRows[] = [
                'line' => $index + 2, // header is line 1
                'action' => $action,
                'record_id' => $existing?->id,
                'valid' => $valid,
                'errors' => $errors,
                'attributes' => $parsed['attributes'],
                'values' => $parsed['values'],
                'categories' => $parsed['categories'],
            ];

            $summary['total']++;
            $summary[$valid ? 'valid' : 'invalid']++;
            if ($valid) {
                $summary[$action]++;
            }
        }

        return [
            'columns' => $mapping,
            'rows' => $previewRows,
            'summary' => $summary,
        ];
    }

    /**
     * Import the valid rows. Invalid rows are skipped and reported.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int, errors: array<int, array<string, array<int, string>>>}
     */
    public function import(Entity $entity, array $rows, string $mode = self::MODE_CREATE): array
    {
        $preview = $this->preview($entity, $rows, $mode);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($preview['rows'] as $row) {
            if (! $row['valid']) {
                $result['skipped']++;
                $result['errors'][$row['line']] = $row['errors'];

                continue;
            }

            try {
                if ($row['action'] === 'update') {
                    $this->records->update(
                        $entity->id,
                        $row['record_id'],
                        $row['attributes'],
                        $row['values'],
                        $row['categories'] === null ? null : $row['categories'],
                        $entity->tenant_id,
                    );
                    $result['updated']++;
                } else {
                    $this->records->create(
                        $entity->id,
                        $row['attributes'],
                        $row['values'],
                        $row['categories'] ?? [],
                        $entity->tenant_id,
                    );
                    $result['created']++;
                }
            } catch (Throwable $e) {
                $result['skipped']++;
                $result['errors'][$row['line']] = ['row' => [$e->getMessage()]];
            }
        }

        return $result;
    }

    /**
     * Map file headers to record attributes or field keys (by key or label, case-insensitive).
     *
     * @param  array<int, string>  $headers
     * @param  Collection<int, CustomField>  $fields
     * @return array{mapped: array<string, string>, unknown: array<int, string>}
     */
    public function mapColumns(array $headers, Collection $fields): array
    {
        $mapped = [];
        $unknown = [];

        foreach ($headers as $header) {
            $normalized = Str::lower(trim((string) $header));

            if (in_array($normalized, self::ATTRIBUTE_COLUMNS, true)) {
                $mapped[$header] = $normalized;

                continue;
            }

            $field = $fields->first(fn (CustomField $f) => Str::lower($f->key) === $normalized || Str::lower($f->label) === $normalized);

            if ($field) {
                $mapped[$header] = 'field:' . $field->key;
            } elseif (! in_array($normalized, ['created_at', 'updated_at', 'deleted_at'], true)) {
                $unknown[] = (string) $header;
            }
        }

        return ['mapped' => $mapped, 'unknown' => $unknown];
    }

    /**
     * Convert a raw cell to the PHP value expected by the field.
     */
    public function parseValue(?CustomField $field, ?string $raw): mixed
    {
        if ($raw === null || $field === null) {
            return $raw;
        }

        $json = in_array($raw[0] ?? '', ['[', '{'], true) ? json_decode($raw, true) : null;

        if (FieldTypes::isTranslatable($field)) {
            return is_array($json) ? $json : [FieldTypes::fallbackLocale() => $raw];
        }

        $multiple = FieldTypes::isMultiple($field);

        return match ($field->type) {
            'boolean' => $this->parseBoolean($raw),
            'number' => is_numeric($raw) ? $raw + 0 : $raw,
            'multiselect', 'tags' => is_array($json) ? $json : array_values(array_filter(array_map('trim', explode('|', $raw)), fn ($v) => $v !== '')),
            'json', 'repeater', 'address', 'link' => $json ?? $raw,
            'relationship' => $multiple
                ? (is_array($json) ? $json : array_values(array_filter(array_map('trim', explode('|', $raw)), fn ($v) => $v !== '')))
                : (is_numeric($raw) ? (int) $raw : $raw),
            'image', 'file' => $multiple ? ($json ?? array_values(array_filter(explode('|', $raw)))) : $raw,
            default => $raw,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{mapped: array<string, string>, unknown: array<int, string>}  $mapping
     * @param  Collection<int, CustomField>  $fields
     * @return array{attributes: array<string, mixed>, values: array<string, mixed>, categories: array<int, string>|null}
     */
    protected function parseRow(array $row, array $mapping, Collection $fields): array
    {
        $attributes = [];
        $values = [];
        $categories = null;
        $fieldsByKey = $fields->keyBy('key');

        foreach ($mapping['mapped'] as $header => $target) {
            $raw = $this->unescape($row[$header] ?? null);

            if (str_starts_with($target, 'field:')) {
                $key = substr($target, 6);
                $values[$key] = $this->parseValue($fieldsByKey->get($key), $raw);

                continue;
            }

            if ($target === 'categories') {
                $categories = $raw === null ? [] : array_values(array_filter(array_map('trim', preg_split('/[|,]/', $raw)), fn ($v) => $v !== ''));
            } elseif ($raw !== null) {
                $attributes[$target] = $target === 'order' ? (int) $raw : $raw;
            }
        }

        return ['attributes' => $attributes, 'values' => $values, 'categories' => $categories];
    }

    protected function parseBoolean(string $raw): bool | string
    {
        $normalized = Str::lower(trim($raw));

        return match (true) {
            in_array($normalized, ['1', 'true', 'yes', 'y', 'si', 'sí', 'on'], true) => true,
            in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true) => false,
            default => $raw,
        };
    }

    /**
     * Strip the formula-protection quote added by RecordExporter and normalize blanks.
     */
    protected function unescape(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, "'") && in_array($value[1] ?? '', ['=', '+', '-', '@'], true)) {
            return substr($value, 1);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function findExisting(Entity $entity, array $attributes): ?EntityRecord
    {
        if (! empty($attributes['id']) && is_numeric($attributes['id'])) {
            $record = $entity->records()->where('id', (int) $attributes['id'])->first();

            if ($record) {
                return $record;
            }
        }

        if (! empty($attributes['slug'])) {
            return $entity->records()->where('slug', Str::slug((string) $attributes['slug']))->first();
        }

        return null;
    }

    protected function ensureValidMode(string $mode): void
    {
        if (! in_array($mode, [self::MODE_CREATE, self::MODE_UPSERT], true)) {
            throw new RuntimeException("Invalid import mode [{$mode}]. Use 'create' or 'upsert'.");
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    protected function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = $this->readCsvHandle($handle);
        fclose($handle);

        return $rows;
    }

    /**
     * @param  resource  $handle
     * @return array<int, array<string, string|null>>
     */
    protected function readCsvHandle($handle): array
    {
        $delimiter = (string) config('flex-fields.import_export.csv_delimiter', ',');
        $header = null;
        $rows = [];

        while (($line = fgetcsv($handle, null, $delimiter, escape: '')) !== false) {
            if ($line === [null]) {
                continue; // blank line
            }

            if ($header === null) {
                $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $line[0]);
                $header = array_map(fn ($h) => trim((string) $h), $line);

                continue;
            }

            $rows[] = $this->combine($header, $line);
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    protected function readXlsx(string $path): array
    {
        if (! class_exists(XlsxReader::class)) {
            throw new RuntimeException('Reading .xlsx files requires openspout/openspout.');
        }

        $reader = new XlsxReader;
        $reader->open($path);

        $header = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($cell) => match (true) {
                    $cell instanceof DateTimeInterface => $cell->format('Y-m-d H:i:s'),
                    is_bool($cell) => $cell ? '1' : '0',
                    $cell === null => null,
                    default => (string) $cell,
                }, $row->toArray());

                if ($header === null) {
                    $header = array_map(fn ($h) => trim((string) $h), $cells);

                    continue;
                }

                if (array_filter($cells, fn ($c) => $c !== null && $c !== '') === []) {
                    continue;
                }

                $rows[] = $this->combine($header, $cells);
            }

            break; // first sheet only
        }

        $reader->close();

        return $rows;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, mixed>  $cells
     * @return array<string, string|null>
     */
    protected function combine(array $header, array $cells): array
    {
        $row = [];

        foreach ($header as $i => $column) {
            if ($column === '') {
                continue;
            }

            $row[$column] = isset($cells[$i]) ? (string) $cells[$i] : null;
        }

        return $row;
    }
}
