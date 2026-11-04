<?php

namespace Tests\Unit;

use App\Actions\Checks\CheckDefinitionValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckDefinitionValidatorTest extends TestCase
{
    public function test_it_accepts_a_valid_definition(): void
    {
        $validated = app(CheckDefinitionValidator::class)->validate($this->definition());

        $this->assertSame('/health', $validated['configuration']['path']);
        $this->assertSame(204, $validated['assertions'][0]['expected']);
    }

    /** @dataProvider invalidPathProvider */
    public function test_it_rejects_non_relative_paths(string $path): void
    {
        $this->expectException(ValidationException::class);

        app(CheckDefinitionValidator::class)->validate(
            array_replace_recursive($this->definition(), ['configuration' => ['path' => $path]])
        );
    }

    public function test_it_requires_a_non_associative_assertion_list(): void
    {
        $validator = app(CheckDefinitionValidator::class);

        try {
            $validator->validate(array_replace($this->definition(), ['assertions' => []]));
            $this->fail('Uma lista vazia deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assertions', $exception->errors());
        }

        try {
            $validator->validate(array_replace($this->definition(), ['assertions' => ['field' => 'status_code']]));
            $this->fail('Um objeto associativo deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assertions', $exception->errors());
        }
    }

    public function test_it_does_not_require_http_configuration_for_non_http_executors(): void
    {
        $validated = app(CheckDefinitionValidator::class)->validate(array_replace(
            $this->definition(),
            ['executor_type' => 'agent', 'configuration' => []]
        ));

        $this->assertSame([], $validated['configuration']);
    }

    public function test_it_reports_malformed_assertions_without_throwing_a_php_error(): void
    {
        try {
            app(CheckDefinitionValidator::class)->validate(array_replace(
                $this->definition(),
                ['assertions' => [['field' => [], 'operator' => 'equals', 'expected' => 200]]]
            ));
            $this->fail('Uma condição malformada deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assertions.0.field', $exception->errors());
        }
    }

    public function test_it_rejects_incompatible_status_code_conditions(): void
    {
        $validator = app(CheckDefinitionValidator::class);

        try {
            $validator->validate($this->definition([
                'field' => 'status_code',
                'operator' => 'greater_than',
                'expected' => 'abc',
            ]));
            $this->fail('Um status_code textual deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assertions.0.expected', $exception->errors());
        }

        try {
            $validator->validate($this->definition([
                'field' => 'status_code',
                'operator' => 'contains',
                'expected' => 200,
            ]));
            $this->fail('O operador contains deveria ser rejeitado para status_code.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assertions.0.operator', $exception->errors());
        }

        try {
            $validator->validate($this->definition([
                'field' => 'status_code',
                'operator' => 'equals',
                'expected' => 99,
            ]));
            $this->fail('Um status_code fora da faixa HTTP deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assertions.0.expected', $exception->errors());
        }
    }

    public static function invalidPathProvider(): array
    {
        return [
            'absolute url' => ['https://example.com/health'],
            'double slash host' => ['//example.com/health'],
            'backslash' => ['/health\\status'],
            'missing leading slash' => ['health'],
        ];
    }

    private function definition(array $assertion = []): array
    {
        return [
            'name' => 'Health check',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => [
                'path' => '/health',
                'method' => 'GET',
                'timeout' => 10,
                'follow_redirects' => false,
            ],
            'assertions' => [$assertion ?: [
                'field' => 'status_code',
                'operator' => 'equals',
                'expected' => 204,
            ]],
        ];
    }
}
