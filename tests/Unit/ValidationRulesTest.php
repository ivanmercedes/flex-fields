<?php

declare(strict_types=1);

namespace IvanMercedes\FlexFields\Tests\Unit;

use InvalidArgumentException;
use IvanMercedes\FlexFields\Support\ValidationRules;
use IvanMercedes\FlexFields\Tests\TestCase;

class ValidationRulesTest extends TestCase
{
    public function test_converts_between_rule_strings_and_builder_state(): void
    {
        $state = ValidationRules::toBuilderState(['required', 'min:3', 'regex:/^[A-Z]:x/', 'alpha']);

        $this->assertSame([
            ['rule' => 'min', 'value' => '3'],
            ['rule' => 'regex', 'value' => '/^[A-Z]:x/'],
            ['rule' => 'alpha', 'value' => null],
        ], $state);

        $this->assertSame(['min:3', 'regex:/^[A-Z]:x/', 'alpha'], ValidationRules::fromBuilderState($state));
    }

    public function test_builder_ignores_empty_items_and_values_for_valueless_rules(): void
    {
        $this->assertSame(['email', 'max:10'], ValidationRules::fromBuilderState([
            ['rule' => 'email', 'value' => 'ignored'],
            ['rule' => '', 'value' => '3'],
            ['rule' => 'max', 'value' => '10'],
            ['rule' => 'max', 'value' => '10'],
        ]));
    }

    public function test_sanitize_rejects_unknown_rules(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValidationRules::sanitize(['exists:users,id']);
    }

    public function test_sanitize_requires_parameters_and_valid_regex(): void
    {
        try {
            ValidationRules::sanitize(['min']);
            $this->fail('Expected exception for missing parameter.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('requires a parameter', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        ValidationRules::sanitize(['regex:/[unclosed/']);
    }

    public function test_sanitize_drops_presence_rules_and_for_field_filters(): void
    {
        $this->assertSame(['max:5'], ValidationRules::sanitize(['required', 'nullable', 'max:5', '']));
        $this->assertSame(['max:5'], ValidationRules::forField(['required', 'max:5']));
        $this->assertSame(['max:5', 'regex:/^a/'], ValidationRules::forField(['string', 'foo', 'exists:users,id', 'min', 'max:5', 'regex:/[bad/', 'regex:/^a/']));
        $this->assertArrayHasKey('regex', ValidationRules::options());
        $this->assertTrue(ValidationRules::needsValue('between'));
        $this->assertFalse(ValidationRules::needsValue('alpha'));
    }
}
