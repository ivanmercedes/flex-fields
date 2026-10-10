<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Unit;

use InvalidArgumentException;
use IvanMercedes\FlexFields\Support\ConditionEvaluator;
use PHPUnit\Framework\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    public function test_empty_rules_always_pass(): void
    {
        $this->assertTrue(ConditionEvaluator::passes(null, []));
        $this->assertTrue(ConditionEvaluator::passes([], ['a' => 1]));
        $this->assertNull(ConditionEvaluator::normalize(['match' => 'all', 'conditions' => []]));
    }

    public function test_normalizes_bare_list_of_conditions(): void
    {
        $normalized = ConditionEvaluator::normalize([
            ['field' => 'type', 'value' => 'digital'],
            ['field' => 'stock', 'operator' => 'filled', 'value' => 'ignored'],
        ]);

        $this->assertSame('all', $normalized['match']);
        $this->assertSame('equals', $normalized['conditions'][0]['operator']);
        $this->assertNull($normalized['conditions'][1]['value']);
    }

    public function test_invalid_operator_and_match_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConditionEvaluator::normalize([['field' => 'a', 'operator' => 'like']]);
    }

    public function test_invalid_match_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConditionEvaluator::normalize(['match' => 'some', 'conditions' => [['field' => 'a']]]);
    }

    public function test_all_and_any_matching(): void
    {
        $rules = ['match' => 'all', 'conditions' => [
            ['field' => 'type', 'operator' => 'equals', 'value' => 'digital'],
            ['field' => 'price', 'operator' => 'gt', 'value' => 10],
        ]];

        $this->assertTrue(ConditionEvaluator::passes($rules, ['type' => 'digital', 'price' => '25']));
        $this->assertFalse(ConditionEvaluator::passes($rules, ['type' => 'digital', 'price' => 5]));

        $rules['match'] = 'any';
        $this->assertTrue(ConditionEvaluator::passes($rules, ['type' => 'physical', 'price' => 50]));
        $this->assertFalse(ConditionEvaluator::passes($rules, ['type' => 'physical', 'price' => 1]));
    }

    public function test_operators(): void
    {
        $this->assertTrue(ConditionEvaluator::check('equals', 5, '5'));
        $this->assertTrue(ConditionEvaluator::check('equals', ['a', 'b'], 'b'));
        $this->assertTrue(ConditionEvaluator::check('equals', true, '1'));
        $this->assertTrue(ConditionEvaluator::check('not_equals', 'a', 'b'));
        $this->assertTrue(ConditionEvaluator::check('in', 'red', 'red, blue'));
        $this->assertTrue(ConditionEvaluator::check('in', ['x', 'blue'], ['blue']));
        $this->assertTrue(ConditionEvaluator::check('not_in', 'green', 'red,blue'));
        $this->assertTrue(ConditionEvaluator::check('contains', 'hello world', 'world'));
        $this->assertTrue(ConditionEvaluator::check('contains', ['php', 'laravel'], 'laravel'));
        $this->assertTrue(ConditionEvaluator::check('filled', 'x', null));
        $this->assertTrue(ConditionEvaluator::check('blank', [], null));
        $this->assertTrue(ConditionEvaluator::check('gte', 10, 10));
        $this->assertTrue(ConditionEvaluator::check('lt', '3', 4));
        $this->assertTrue(ConditionEvaluator::check('lte', 4, 4));
        $this->assertFalse(ConditionEvaluator::check('gt', 'abc', 1));
        $this->assertTrue(ConditionEvaluator::check('is_true', 'on', null));
        $this->assertTrue(ConditionEvaluator::check('is_false', false, null));
    }

    public function test_resolver_callable_and_controlling_keys(): void
    {
        $rules = [['field' => 'has_discount', 'operator' => 'is_true'], ['field' => 'has_discount', 'operator' => 'filled']];

        $this->assertTrue(ConditionEvaluator::passes($rules, fn (string $key) => $key === 'has_discount'));
        $this->assertSame(['has_discount'], ConditionEvaluator::controllingKeys($rules));
        $this->assertSame([], ConditionEvaluator::controllingKeys(null));
    }
}
