<?php

namespace Tests\Unit;

use App\Services\Checks\AssertionEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AssertionEvaluatorTest extends TestCase
{
    public function test_equals_still_uses_strict_equality(): void
    {
        $evaluator = new AssertionEvaluator;
        $assertion = ['field' => 'status_code', 'operator' => 'equals', 'expected' => 200];

        $this->assertTrue($evaluator->evaluate(['status_code' => 200], $assertion)['passed']);
        $this->assertFalse($evaluator->evaluate(['status_code' => '200'], $assertion)['passed']);
    }

    public function test_numeric_operators_compare_numbers(): void
    {
        $evaluator = new AssertionEvaluator;

        foreach ([
            ['less_than', 500, 300, false],
            ['less_than', 2, 2.5, true],
            ['greater_than', 2.5, 2, true],
            ['greater_than', 2, 2, false],
            ['greater_than_or_equal', 2, 2.0, true],
            ['greater_than_or_equal', 1, 2, false],
            ['less_than_or_equal', 2.0, 2, true],
            ['less_than_or_equal', 3, 2, false],
        ] as [$operator, $actual, $expected, $passed]) {
            $this->assertSame($passed, $evaluator->evaluate(['value' => $actual], [
                'field' => 'value', 'operator' => $operator, 'expected' => $expected,
            ])['passed']);
        }
    }

    public function test_operators_reject_incompatible_types_on_either_side(): void
    {
        $evaluator = new AssertionEvaluator;

        foreach (['greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] as $operator) {
            $valid = 300;
            $invalidValues = ['abc', '300', null, false, []];

            foreach ($invalidValues as $invalid) {
                foreach ([[$invalid, $valid], [$valid, $invalid]] as [$actual, $expected]) {
                    try {
                        $evaluator->evaluate(['value' => $actual], [
                            'field' => 'value', 'operator' => $operator, 'expected' => $expected,
                        ]);
                        $this->fail('Tipos incompatíveis devem gerar erro de avaliação.');
                    } catch (InvalidArgumentException $exception) {
                        $this->assertStringContainsString($operator, $exception->getMessage());
                    }
                }
            }
        }
    }

    public function test_not_equals_uses_strict_comparison(): void
    {
        $evaluator = new AssertionEvaluator;
        $assertion = ['field' => 'value', 'operator' => 'not_equals', 'expected' => 200];
        $this->assertFalse($evaluator->evaluate(['value' => 200], $assertion)['passed']);
        $this->assertTrue($evaluator->evaluate(['value' => '200'], $assertion)['passed']);
    }

    public function test_text_operators_support_substrings_and_case_insensitive_matching(): void
    {
        foreach (['contains' => true, 'not_contains' => false, 'contains_ignore_case' => true, 'not_contains_ignore_case' => false] as $operator => $passed) {
            $this->assertSame($passed, (new AssertionEvaluator)->evaluate(['body' => 'texto'], [
                'field' => 'body', 'operator' => $operator, 'expected' => str_contains($operator, 'ignore_case') ? 'TEXTO' : 'texto',
            ])['passed']);
        }
    }

    public function test_missing_field_throws_evaluation_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Campo ausente no resultado: body');

        (new AssertionEvaluator)->evaluate([], [
            'field' => 'body', 'operator' => 'equals', 'expected' => 'erro',
        ]);
    }
}
