<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Services;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use IvanMercedes\FlexFields\Models\CustomField;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Models\EntityRecord;
use IvanMercedes\FlexFields\Support\MediaLibrary;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports entity records to CSV without extra dependencies.
 *
 * Array values (tags, multiselect, repeater, address, translations...) are
 * written as JSON so the file can be imported back with RecordImporter.
 */
class RecordExporter
{
    public const BASE_COLUMNS = ['id', 'title', 'slug', 'status', 'categories', 'created_at', 'updated_at'];

    /**
     * @return array<int, string>
     */
    public function columns(Entity $entity): array
    {
        return [...self::BASE_COLUMNS, ...$this->exportableFields($entity)->pluck('key')->all()];
    }

    /**
     * @param  iterable<EntityRecord>|Builder|Relation  $records
     * @return Generator<int, array<int, string>>
     */
    public function rows(Entity $entity, iterable | Builder | Relation $records): Generator
    {
        $fields = $this->exportableFields($entity);

        if ($records instanceof Builder || $records instanceof Relation) {
            $records = $records->with(['categories', 'fieldValues.customField'])->lazyById(200);
        }

        foreach ($records as $record) {
            $data = $record->data;

            $row = [
                (string) $record->id,
                $this->cell($record->title),
                $this->cell($record->slug),
                $this->cell($record->status),
                $this->cell($record->categories->pluck('slug')->implode('|')),
                $this->cell($record->created_at),
                $this->cell($record->updated_at),
            ];

            foreach ($fields as $field) {
                $row[] = $this->cell($data[$field->key] ?? null);
            }

            yield $row;
        }
    }

    /**
     * Write a full CSV (header + rows) to a stream resource.
     *
     * @param  resource  $handle
     * @param  iterable<EntityRecord>|Builder|Relation  $records
     */
    public function write(Entity $entity, iterable | Builder | Relation $records, $handle, bool $withBom = true): void
    {
        $delimiter = (string) config('flex-fields.import_export.csv_delimiter', ',');

        if ($withBom) {
            fwrite($handle, "\xEF\xBB\xBF");
        }

        fputcsv($handle, $this->columns($entity), $delimiter, escape: '');

        foreach ($this->rows($entity, $records) as $row) {
            fputcsv($handle, $row, $delimiter, escape: '');
        }
    }

    /**
     * @param  iterable<EntityRecord>|Builder|Relation  $records
     */
    public function toCsv(Entity $entity, iterable | Builder | Relation $records, bool $withBom = false): string
    {
        $handle = fopen('php://temp', 'r+');
        $this->write($entity, $records, $handle, $withBom);
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @param  iterable<EntityRecord>|Builder|Relation  $records
     */
    public function download(Entity $entity, iterable | Builder | Relation $records, ?string $filename = null): StreamedResponse
    {
        $filename ??= Str::slug($entity->slug) . '-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($entity, $records) {
            $handle = fopen('php://output', 'w');
            $this->write($entity, $records, $handle);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Serialize a value for a CSV cell, neutralizing spreadsheet formulas.
     */
    public function cell(mixed $value): string
    {
        $string = match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };

        // CSV/formula injection protection (=, +, -, @, tab, CR at the start of a cell).
        if ($string !== '' && in_array($string[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($string)) {
            return "'" . $string;
        }

        return $string;
    }

    /**
     * @return Collection<int, CustomField>
     */
    protected function exportableFields(Entity $entity): Collection
    {
        return $entity->customFields()->get()->reject(fn (CustomField $field) => MediaLibrary::handles($field))->values();
    }
}
