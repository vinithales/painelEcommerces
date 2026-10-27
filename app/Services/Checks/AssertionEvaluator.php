<?php

namespace App\Services\Checks;

class AssertionEvaluator
{
    public function evaluate(array $result, array $assertion): array
    {
        $field = $assertion['field'];

        if (!array_key_exists($field, $result)) {
            throw new \InvalidArgumentException(
                "Campo ausente no resultado: {$field}"
            );
        }
        $actual = $result[$field];
        $expected = $assertion['expected'];
        $operator = $assertion['operator'];

        if (in_array($operator, ['greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'], true)) {
            $actualIsNumber = is_int($actual) || is_float($actual);
            $expectedIsNumber = is_int($expected) || is_float($expected);

            if (!$actualIsNumber || !$expectedIsNumber) {
                throw new \InvalidArgumentException("O operador {$operator} exige números como resultado e valor esperado.");
            }
        }

        if (in_array($operator, ['contains', 'not_contains'], true)) {
            if (!is_string($actual) || !is_string($expected)) {
                throw new \InvalidArgumentException(
                    "O operador {$operator} exige dois valores de texto."
                );
            }
        }

        $passed = match ($operator) {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'greater_than' => $actual > $expected,
            'less_than' => $actual < $expected,
            'greater_than_or_equal' => $actual >= $expected,
            'less_than_or_equal' => $actual <= $expected,
            'contains' => str_contains($actual, $expected),
            'not_contains' => !str_contains($actual, $expected),
            default => throw new \InvalidArgumentException(
                "Operador inválido: {$assertion['operator']}"
            ),
        };

        return [
            'field' => $field,
            'operator' => $assertion['operator'],
            'expected' => $expected,
            'actual' => $actual,
            'passed' => $passed,
        ];
    }
}
