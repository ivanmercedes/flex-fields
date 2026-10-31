<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Feature;

use IvanMercedes\FlexFields\Ai\Tools\ListRecordsTool;
use IvanMercedes\FlexFields\Models\Entity;
use IvanMercedes\FlexFields\Services\RecordDomainService;
use IvanMercedes\FlexFields\Tests\Fixtures\User;
use IvanMercedes\FlexFields\Tests\TestCase;
use Laravel\Ai\Tools\Request;

class RecordFiltersTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $brand = Entity::create(['name' => 'Brand', 'slug' => 'brand']);
        $product = Entity::create(['name' => 'Product', 'slug' => 'product']);
        $product->customFields()->create(['label' => 'Price', 'key' => 'price', 'type' => 'number']);
        $product->customFields()->create(['label' => 'Kind', 'key' => 'kind', 'type' => 'select', 'options' => ['physical' => 'Physical', 'digital' => 'Digital']]);
        $product->customFields()->create(['label' => 'Featured', 'key' => 'featured', 'type' => 'boolean']);
        $product->customFields()->create(['label' => 'Tags', 'key' => 'tags', 'type' => 'tags']);
        $product->customFields()->create(['label' => 'Launch', 'key' => 'launch', 'type' => 'date']);
        $product->customFields()->create(['label' => 'Body', 'key' => 'body', 'type' => 'richtext']);
        $product->customFields()->create(['label' => 'Brands', 'key' => 'brands', 'type' => 'relationship', 'settings' => ['related_entity' => 'brand', 'multiple' => true]]);

        $records = new RecordDomainService;
        $apple = $records->create('brand', ['title' => 'Apple']);
        $acme = $records->create('brand', ['title' => 'Acme']);

        $records->create('product', ['title' => 'Phone'], ['price' => 900, 'kind' => 'physical', 'featured' => true, 'tags' => ['tech', 'mobile'], 'launch' => '2026-03-01', 'brands' => [$apple->id]]);
        $records->create('product', ['title' => 'Ebook'], ['price' => 9, 'kind' => 'digital', 'tags' => ['books'], 'launch' => '2025-11-15', 'brands' => [$acme->id, $apple->id]]);
        $records->create('product', ['title' => 'Cable'], ['price' => 19.5, 'kind' => 'physical', 'featured' => false, 'body' => '<p>Long</p>']);

        $this->user = $this->makeUser();
    }

    public function test_comparison_operators(): void
    {
        $this->assertEqualsCanonicalizing(['Phone', 'Cable'], $this->titles(['filter' => ['kind' => 'physical']]));
        $this->assertEqualsCanonicalizing(['Ebook'], $this->titles(['filter' => ['kind' => ['ne' => 'physical']]]));
        $this->assertEqualsCanonicalizing(['Phone', 'Cable'], $this->titles(['filter' => ['price' => ['gte' => 10]]]), 'Numbers are compared numerically.');
        $this->assertEqualsCanonicalizing(['Ebook', 'Cable'], $this->titles(['filter' => ['price' => ['gt' => 1, 'lt' => 100]]]));
        $this->assertEqualsCanonicalizing(['Phone'], $this->titles(['filter' => ['launch' => ['gte' => '2026-01-01']]]));
        $this->assertEqualsCanonicalizing(['Phone', 'Ebook'], $this->titles(['filter' => ['kind' => ['in' => 'digital,physical'], 'price' => ['ne' => 19.5]]]));
    }

    public function test_booleans_lists_relationships_and_nulls(): void
    {
        $this->assertSame(['Phone'], $this->titles(['filter' => ['featured' => 'true']]));
        $this->assertEqualsCanonicalizing(['Ebook', 'Cable'], $this->titles(['filter' => ['featured' => 'false']]), 'Missing booleans are false.');
        $this->assertEqualsCanonicalizing(['Phone'], $this->titles(['filter' => ['tags' => ['contains' => 'mobile']]]));
        $this->assertEqualsCanonicalizing(['Phone', 'Ebook'], $this->titles(['filter' => ['brands' => Entity::where('slug', 'brand')->first()->records()->where('title', 'Apple')->value('id')]]));
        $this->assertEqualsCanonicalizing(['Cable'], $this->titles(['filter' => ['tags' => ['null' => 'true']]]));
        $this->assertEqualsCanonicalizing(['Cable'], $this->titles(['filter' => ['body' => ['null' => 'false']]]));
    }

    public function test_sort_by_fields_and_columns(): void
    {
        $this->assertSame(['Phone', 'Cable', 'Ebook'], $this->titles(['sort' => '-price']));
        $this->assertSame(['Ebook', 'Cable', 'Phone'], $this->titles(['sort' => 'price']));
        $this->assertSame(['Cable', 'Ebook', 'Phone'], $this->titles(['sort' => 'title']));
    }

    public function test_invalid_filters_return_422(): void
    {
        $this->actingAs($this->user)->getJson('/api/flex/product?filter[nope]=1')->assertUnprocessable()->assertJsonValidationErrors('filter.nope');
        $this->actingAs($this->user)->getJson('/api/flex/product?filter[price][like]=1')->assertUnprocessable();
        $this->actingAs($this->user)->getJson('/api/flex/product?filter[body][eq]=x')->assertUnprocessable();
        $this->actingAs($this->user)->getJson('/api/flex/product?filter[price][gt]=abc')->assertUnprocessable();
        $this->actingAs($this->user)->getJson('/api/flex/product?sort=body')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_the_list_records_tool_filters_and_sorts(): void
    {
        $result = (new ListRecordsTool)->run(new Request([
            'entity' => 'product',
            'filters' => json_encode(['kind' => 'physical']),
            'sort' => '-price',
        ]));

        $this->assertFalse($result->isError);
        $this->assertSame(['Phone', 'Cable'], array_column(json_decode($result->content, true)['records'], 'title'));

        $this->assertTrue((new ListRecordsTool)->run(new Request(['entity' => 'product', 'filters' => '{"nope": 1}']))->isError);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, string>
     */
    protected function titles(array $query): array
    {
        return collect($this->actingAs($this->user)->getJson('/api/flex/product?' . http_build_query($query))->assertOk()->json('data'))
            ->pluck('title')
            ->all();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('flex-fields.api.enabled', true);
        $app['config']->set('flex-fields.api.middleware', ['api', 'auth']);
    }
}
