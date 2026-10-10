<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Services\RecordExporter;
use IvanMercedes\FlexFields\Services\RecordImporter;
use IvanMercedes\FlexFields\Tests\TestCase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class ImportExportTest extends TestCase
{
    protected Entity $entity;

    protected RecordDomainService $records;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $this->entity->customFields()->create(['label' => 'Price', 'key' => 'price', 'type' => 'number', 'is_required' => true]);
        $this->entity->customFields()->create(['label' => 'In Stock', 'key' => 'in_stock', 'type' => 'boolean']);
        $this->entity->customFields()->create(['label' => 'Tags', 'key' => 'tags', 'type' => 'tags']);
        $this->entity->customFields()->create([
            'label' => 'Size', 'key' => 'size', 'type' => 'select',
            'options' => [['value' => 's', 'label' => 'Small'], ['value' => 'l', 'label' => 'Large']],
        ]);
        $this->entity->categories()->create(['name' => 'Phones', 'slug' => 'phones']);

        $this->records = new RecordDomainService;
    }

    public function test_export_writes_header_rows_and_json_for_arrays(): void
    {
        $this->records->create('product', ['title' => 'Phone', 'slug' => 'phone'], ['price' => 99.5, 'in_stock' => true, 'tags' => ['a', 'b'], 'size' => 's'], ['phones']);

        $rows = array_map('str_getcsv', explode("\n", trim((new RecordExporter)->toCsv($this->entity, $this->entity->records()))));

        $this->assertSame(['id', 'title', 'slug', 'status', 'categories', 'created_at', 'updated_at', 'price', 'in_stock', 'tags', 'size'], $rows[0]);
        $this->assertSame('Phone', $rows[1][1]);
        $this->assertSame('phones', $rows[1][4]);
        $this->assertSame('99.5', $rows[1][7]);
        $this->assertSame('1', $rows[1][8]);
        $this->assertSame('["a","b"]', $rows[1][9]);
    }

    public function test_export_neutralizes_spreadsheet_formulas(): void
    {
        $exporter = new RecordExporter;

        $this->assertSame("'=HYPERLINK(\"x\")", $exporter->cell('=HYPERLINK("x")'));
        $this->assertSame("'@SUM(A1)", $exporter->cell('@SUM(A1)'));
        $this->assertSame('-15', $exporter->cell('-15'));
        $this->assertSame('', $exporter->cell(null));
    }

    public function test_export_download_streams_csv(): void
    {
        $this->records->create('product', ['title' => 'Phone'], ['price' => 1]);

        $response = (new RecordExporter)->download($this->entity, $this->entity->records());

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Phone', $content);
    }

    public function test_template_contains_importable_columns(): void
    {
        $importer = new RecordImporter;

        $this->assertSame(['title', 'slug', 'status', 'categories', 'price', 'in_stock', 'tags', 'size'], $importer->templateColumns($this->entity));
        $this->assertStringContainsString('title,slug,status,categories,price', $importer->templateCsv($this->entity));
    }

    public function test_preview_validates_rows_without_writing(): void
    {
        $importer = new RecordImporter;
        $rows = $importer->parseCsvString(implode("\n", [
            "\xEF\xBB\xBFtitle,status,categories,Price,in_stock,tags,size,unknown",
            'Phone,draft,phones,10,yes,a|b,s,x',
            'Broken,invalid,missing-cat,abc,,,xl,',
            'No price,,,,,,,',
        ]));

        $preview = $importer->preview($this->entity, $rows);

        $this->assertSame(['unknown'], $preview['columns']['unknown']);
        $this->assertSame('field:price', $preview['columns']['mapped']['Price']);
        $this->assertSame(['total' => 3, 'valid' => 1, 'invalid' => 2, 'create' => 1, 'update' => 0], $preview['summary']);

        $first = $preview['rows'][0];
        $this->assertTrue($first['valid']);
        $this->assertSame(2, $first['line']);
        $this->assertTrue($first['values']['in_stock']);
        $this->assertSame(['a', 'b'], $first['values']['tags']);

        $broken = $preview['rows'][1];
        $this->assertArrayHasKey('status', $broken['errors']);
        $this->assertArrayHasKey('categories', $broken['errors']);
        $this->assertArrayHasKey('price', $broken['errors']);
        $this->assertArrayHasKey('size', $broken['errors']);

        $this->assertArrayHasKey('price', $preview['rows'][2]['errors']);
        $this->assertDatabaseCount('ff_entity_records', 0);
    }

    public function test_import_creates_valid_rows_and_reports_errors(): void
    {
        $importer = new RecordImporter;
        $rows = $importer->parseCsvString("title,price,categories\nPhone,10,phones\nBad,not-a-number,\nTablet,20,");

        $result = $importer->import($this->entity, $rows);

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertArrayHasKey(3, $result['errors']);

        $phone = $this->entity->records()->where('title', 'Phone')->first();
        $this->assertEquals(10, $phone->getValue('price'));
        $this->assertSame(['phones'], $phone->categories->pluck('slug')->all());
        $this->assertSame('created', $phone->revisions()->first()->event);
    }

    public function test_upsert_updates_existing_records_by_slug(): void
    {
        $this->records->create('product', ['title' => 'Phone', 'slug' => 'phone'], ['price' => 10]);

        $importer = new RecordImporter;
        $rows = $importer->parseCsvString("slug,title,price\nphone,Phone v2,15\nnew-one,New,5");

        $preview = $importer->preview($this->entity, $rows, RecordImporter::MODE_UPSERT);
        $this->assertSame(1, $preview['summary']['update']);
        $this->assertSame(1, $preview['summary']['create']);

        $result = $importer->import($this->entity, $rows, RecordImporter::MODE_UPSERT);
        $this->assertSame(['created' => 1, 'updated' => 1, 'skipped' => 0, 'errors' => []], $result);

        $phone = $this->entity->records()->where('slug', 'phone')->first();
        $this->assertSame('Phone v2', $phone->title);
        $this->assertEquals(15, $phone->getValue('price'));
    }

    public function test_export_then_import_roundtrip(): void
    {
        $this->records->create('product', ['title' => '=Danger', 'slug' => 'danger'], ['price' => 3, 'tags' => ['x', 'y'], 'in_stock' => false]);

        $csv = (new RecordExporter)->toCsv($this->entity, $this->entity->records());
        $this->entity->records()->forceDelete();

        $importer = new RecordImporter;
        $result = $importer->import($this->entity, $importer->parseCsvString($csv));

        $this->assertSame(1, $result['created']);
        $record = $this->entity->records()->first();
        $this->assertSame('=Danger', $record->title);
        $this->assertSame(['x', 'y'], $record->getValue('tags'));
        $this->assertFalse($record->getValue('in_stock'));
    }

    public function test_reads_xlsx_files(): void
    {
        $path = sys_get_temp_dir() . '/flex-import-' . uniqid() . '.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['title', 'price', 'in_stock']));
        $writer->addRow(Row::fromValues(['Laptop', 1200, true]));
        $writer->addRow(Row::fromValues(['', '', '']));
        $writer->close();

        $rows = (new RecordImporter)->read($path);
        @unlink($path);

        $this->assertCount(1, $rows);
        $this->assertSame('Laptop', $rows[0]['title']);
        $this->assertSame('1200', $rows[0]['price']);
        $this->assertSame('1', $rows[0]['in_stock']);
    }

    public function test_rejects_unsupported_formats_and_too_many_rows(): void
    {
        $path = sys_get_temp_dir() . '/flex-import-' . uniqid() . '.csv';
        file_put_contents($path, "title\nA\nB\nC");
        config(['flex-fields.import_export.max_rows' => 2]);

        try {
            (new RecordImporter)->read($path);
            $this->fail('Expected max rows exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('maximum allowed is 2', $e->getMessage());
        } finally {
            @unlink($path);
        }

        $this->expectException(RuntimeException::class);
        (new RecordImporter)->read(__FILE__, 'pdf');
    }
}
