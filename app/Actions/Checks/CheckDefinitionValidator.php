<?php

namespace App\Actions\Checks;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CheckDefinitionValidator
{
    public const LEVELS = ['SERVER', 'APP', 'API', 'SITE'];

    public const EXECUTOR_TYPES = ['http', 'browser', 'api', 'agent', 'database'];

    public const ASSERTION_OPERATORS = [
        'contains', 'not_contains', 'contains_ignore_case', 'not_contains_ignore_case',
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
        'contains', 'not_contains', 'contains_ignore_case', 'not_contains_ignore_case',
        'equals',
        'not_equals',
    ];

    public const ASSERTION_FIELDS = [
        'status_code' => 'integer',
        'response_time' => 'integer',
        'url' => 'string',
        'body' => 'string',
        'cache_status' => 'string',
        'console_error_count' => 'integer',
        'page_error_count' => 'integer',
        'headers.x-cache' => 'string',
        'headers.cf-cache-status' => 'string',
        'headers.x-litespeed-cache' => 'string',
        'headers.cache-control' => 'string',
        'headers.age' => 'string',
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
            'assertions.*.field' => ['required', 'string', 'regex:/^(status_code|response_time|url|body|cache_status|console_error_count|page_error_count|headers\.[a-zA-Z0-9_-]+)$/D'],
            'assertions.*.operator' => ['required', Rule::in(self::ASSERTION_OPERATORS)],
            'assertions.*.expected' => 'required',
        ];

        if (($data['executor_type'] ?? null) === 'http') {
            $rules['configuration'] = 'present|array:path,method,timeout,follow_redirects,samples';
            $rules['configuration.path'] = [
                'required',
                'string',
                'max:2048',
                'regex:#^/(?!/)(?!.*\\\\)[^\r\n]*$#D',
            ];
            $rules['configuration.samples'] = 'sometimes|integer|min:1|max:3';
            $rules['configuration.method'] = ['required', Rule::in(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])];
            $rules['configuration.timeout'] = 'required|integer|min:1|max:30';
            $rules['configuration.follow_redirects'] = 'required|boolean';
        }

        if (($data['executor_type'] ?? null) === 'browser') {
            $rules['configuration'] = 'present|array:path,timeout,wait_ms';
            $rules['configuration.path'] = ['required', 'string', 'max:2048', 'regex:#^/(?!/)(?!.*\\\\)[^\r\n]*$#D'];
            $rules['configuration.timeout'] = 'required|integer|min:1|max:30';
            $rules['configuration.wait_ms'] = 'required|integer|min:0|max:10000';
        }

        $validator = Validator::make($data, $rules);

        $validator->after(function ($validator) use ($data): void {
            if (($data['executor_type'] ?? '') === 'http' && ($data['configuration']['samples'] ?? 1) > 1
                && ! in_array($data['configuration']['method'] ?? '', ['GET', 'HEAD'], true)) {
                $validator->errors()->add('configuration.samples', 'Requisições repetidas exigem GET ou HEAD.');
            }
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

                if (! is_string($field) || (! isset(self::ASSERTION_FIELDS[$field]) && ! str_starts_with($field, 'headers.'))) {
                    continue;
                }

                if (in_array($field, ['console_error_count', 'page_error_count'], true) && ($data['executor_type'] ?? '') !== 'browser') {
                    $validator->errors()->add("assertions.{$index}.field", 'Este campo exige o executor browser.');
                }
                if ($field === 'cache_status' && ($data['executor_type'] ?? '') !== 'http') {
                    $validator->errors()->add("assertions.{$index}.field", 'O diagnostico de cache exige o executor HTTP.');
                }

                $operators = (self::ASSERTION_FIELDS[$field] ?? 'string') === 'integer'
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
                } elseif (in_array($field, ['response_time', 'console_error_count', 'page_error_count'], true) && (! is_int($expected) || $expected < 0)) {
                    $validator->errors()->add(
                        "assertions.{$index}.expected",
                        "O campo '{$field}' deve ser um inteiro maior ou igual a zero."
                    );
                } elseif ((self::ASSERTION_FIELDS[$field] ?? 'string') === 'string' && ! is_string($expected)) {
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
