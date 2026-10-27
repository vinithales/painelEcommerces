<?php

namespace App\Actions\Checks;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CheckDefinitionValidator
{
    public const LEVELS = ['SERVER', 'APP', 'API', 'SITE'];

    public const EXECUTOR_TYPES = ['http', 'api', 'agent', 'database'];

    public const ASSERTION_OPERATORS = [
        'equals',
        'not_equals',
        'greater_than',
        'less_than',
        'greater_than_or_equal',
        'less_than_or_equal',
    ];

    public const NUMERIC_ASSERTION_OPERATORS = [
        'equals',
        'not_equals',
        'greater_than',
        'less_than',
        'greater_than_or_equal',
        'less_than_or_equal',
    ];

    public const TEXT_ASSERTION_OPERATORS = [
        'equals',
        'not_equals',
    ];

    public const ASSERTION_FIELDS = [
        'status_code' => 'integer',
        'response_time' => 'integer',
        'url' => 'string',
        'body' => 'string',
    ];

    public function validate(array $data): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'level' => ['required', Rule::in(self::LEVELS)],
            'executor_type' => ['required', Rule::in(self::EXECUTOR_TYPES)],
            'configuration' => 'present|array',
            'assertions' => 'present|array|min:1',
            'assertions.*' => 'array:field,operator,expected',
            'assertions.*.field' => ['required', 'string', Rule::in(array_keys(self::ASSERTION_FIELDS))],
            'assertions.*.operator' => ['required', Rule::in(self::ASSERTION_OPERATORS)],
            'assertions.*.expected' => 'required',
        ];

        if (($data['executor_type'] ?? null) === 'http') {
            $rules['configuration'] = 'present|array:path,method,timeout,follow_redirects';
            $rules['configuration.path'] = [
                'required',
                'string',
                'max:2048',
                'regex:#^/(?!/)(?!.*\\\\)[^\r\n]*$#D',
            ];
            $rules['configuration.method'] = ['required', Rule::in(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])];
            $rules['configuration.timeout'] = 'required|integer|min:1|max:30';
            $rules['configuration.follow_redirects'] = 'required|boolean';
        }

        $validator = Validator::make($data, $rules);

        $validator->after(function ($validator) use ($data): void {
            $assertions = $data['assertions'] ?? null;
            if (! is_array($assertions)) {
                return;
            }

            if (! array_is_list($assertions)) {
                $validator->errors()->add('assertions', 'As condições devem ser informadas como uma lista.');

                return;
            }

            foreach ($assertions as $index => $assertion) {
                if (! is_array($assertion)) {
                    continue;
                }

                $field = $assertion['field'] ?? null;
                $operator = $assertion['operator'] ?? null;
                $expected = $assertion['expected'] ?? null;

                if (! is_string($field) || ! isset(self::ASSERTION_FIELDS[$field])) {
                    continue;
                }

                $operators = self::ASSERTION_FIELDS[$field] === 'integer'
                    ? self::NUMERIC_ASSERTION_OPERATORS
                    : self::TEXT_ASSERTION_OPERATORS;

                if (! in_array($operator, $operators, true)) {
                    $validator->errors()->add(
                        "assertions.{$index}.operator",
                        "O operador '{$operator}' não é compatível com o campo '{$field}'."
                    );
                }

                if ($field === 'status_code' && (! is_int($expected) || $expected < 100 || $expected > 599)) {
                    $validator->errors()->add(
                        "assertions.{$index}.expected",
                        'O status_code deve ser um inteiro entre 100 e 599.'
                    );
                } elseif ($field === 'response_time' && (! is_int($expected) || $expected < 0)) {
                    $validator->errors()->add(
                        "assertions.{$index}.expected",
                        'O response_time deve ser um inteiro maior ou igual a zero.'
                    );
                } elseif (in_array($field, ['url', 'body'], true) && ! is_string($expected)) {
                    $validator->errors()->add(
                        "assertions.{$index}.expected",
                        "O valor esperado para '{$field}' deve ser texto."
                    );
                }
            }
        });

        return $validator->validate();
    }
}
