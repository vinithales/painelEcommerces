<?php

namespace Tests\Unit;

use App\Services\Checks\CheckExecutionFeedback;
use PHPUnit\Framework\TestCase;

class CheckExecutionFeedbackTest extends TestCase
{
    public function test_response_size_error_explains_inconclusive_cache_without_claiming_miss(): void
    {
        $feedback = (new CheckExecutionFeedback)->error('Resposta acima do limite.', [
            'assertions' => [['field' => 'cache_status', 'expected' => 'HIT']],
        ]);

        $this->assertSame('Resposta grande demais para o monitor', $feedback['title']);
        $this->assertStringContainsString('download foi interrompido', $feedback['explanation']);
        $this->assertStringContainsString('não permite concluir', $feedback['impact']);
        $this->assertStringContainsString('limite de leitura', $feedback['action']);
    }

    public function test_known_connection_errors_offer_specific_investigation_steps(): void
    {
        foreach ([
            ['cURL error 28: Operation timed out', 'Tempo limite excedido'],
            ['Navigation timeout', 'Tempo limite excedido'],
            ['DNS indisponível.', 'Não foi possível localizar o domínio'],
            ['cURL error 6: Could not resolve host', 'Não foi possível localizar o domínio'],
            ['Destino privado ou reservado não permitido.', 'Destino bloqueado pelo monitor'],
            ['Limite de redirecionamentos excedido.', 'Redirecionamentos demais'],
            ['cURL error 60: SSL certificate problem', 'Não foi possível validar a conexão HTTPS'],
            ['cURL error 7: Failed to connect', 'Não foi possível conectar ao destino'],
            ['Observacao incompleta: recursos bloqueados', 'A página foi observada parcialmente'],
        ] as [$message, $title]) {
            $feedback = (new CheckExecutionFeedback)->error($message);
            $this->assertSame($title, $feedback['title']);
            $this->assertNotEmpty($feedback['action']);
            $this->assertStringContainsString('inconclusivo', $feedback['impact']);
        }
    }

    public function test_unknown_errors_do_not_invent_a_cause_or_expose_raw_html_in_summary(): void
    {
        $feedback = (new CheckExecutionFeedback)->error('<script>unexpected database error</script>');
        $this->assertSame('Não foi possível concluir a verificação', $feedback['title']);
        $this->assertStringNotContainsString('script', implode(' ', $feedback));
        $this->assertStringContainsString('detalhes técnicos', $feedback['action']);
    }

    public function test_cache_unknown_and_miss_have_distinct_explanations(): void
    {
        $presenter = new CheckExecutionFeedback;
        $unknown = $presenter->cache(['cache_status' => 'UNKNOWN']);
        $this->assertSame('Cache não identificado', $unknown['title']);
        $this->assertStringContainsString('não significa MISS', $unknown['explanation']);
        $bypass = $presenter->cache(['cache_status' => 'UNKNOWN', 'cache_header' => 'cf-cache-status']);
        $this->assertStringContainsString('formato reconhecido', $bypass['explanation']);
        $miss = $presenter->cache(['cache_status' => 'MISS']);
        $this->assertStringContainsString('informou MISS', $miss['explanation']);
        $hit = $presenter->cache(['cache_status' => 'HIT']);
        $this->assertStringContainsString('atendida pelo cache', $hit['title']);
        $this->assertNull($hit['action']);
    }
}
