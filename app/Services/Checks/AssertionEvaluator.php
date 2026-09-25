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
        $passed = $actual === $expected;

        if ($assertion['operator'] !== 'equals') {
            throw new \InvalidArgumentException('Operador ainda não suportado.');
        }
        return [
            'field' => $field,
            'operator' => $assertion['operator'],
            'expected' => $expected,
            'actual' => $actual,
            'passed' => $passed,
        ];

    }

}