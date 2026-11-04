<?php

namespace App\Services\Checks;

use Illuminate\Support\Str;

class CheckExecutionFeedback
{
    public function error(string $message, array $snapshot = []): array
    {
        $text = strtolower(Str::ascii($message));
        [$title, $explanation, $action] = match (true) {
            str_contains($text, 'resposta acima do limite') => [
                'Resposta grande demais para o monitor',
                'O download foi interrompido porque a resposta ultrapassou o limite de tamanho aceito pelo monitor.',
                'Confira o tamanho da resposta da página no servidor ou CDN. Se esse tamanho for esperado, solicite ao responsável pelo monitor uma revisão do limite de leitura.',
            ],
            str_contains($text, 'observacao incompleta') => [
                'A página foi observada parcialmente',
                'O monitor bloqueou um ou mais recursos por limite de tamanho, quantidade ou restrição de destino. Os dados coletados são parciais.',
                'Consulte as requisições bloqueadas nos detalhes para identificar quais recursos impediram a conclusão.',
            ],
            str_contains($text, 'execucao interrompida') => [
                'A execução não terminou',
                'O monitor ficou mais de cinco minutos sem registrar a conclusão desta execução.',
                'Confira se o worker da fila está ativo e tente executar novamente.',
            ],
            str_contains($text, 'tempo limite'), str_contains($text, 'timeout'), str_contains($text, 'timed out'), str_contains($text, 'curl error 28') => [
                'Tempo limite excedido',
                'O monitor não conseguiu concluir a requisição ou a observação da página dentro do tempo permitido.',
                'Confira a disponibilidade e o tempo de resposta da loja. Se o problema persistir, revise o tempo limite da verificação.',
            ],
            str_contains($text, 'dns indisponivel'), str_contains($text, 'could not resolve'), str_contains($text, 'err_name_not_resolved'), str_contains($text, 'curl error 6:') => [
                'Não foi possível localizar o domínio',
                'A consulta DNS não encontrou um endereço para acessar a loja.',
                'Confira o domínio cadastrado e os registros DNS. Tente novamente após confirmar que o domínio resolve no ambiente do monitor.',
            ],
            str_contains($text, 'destino privado'), str_contains($text, 'destino privado ou reservado') => [
                'Destino bloqueado pelo monitor',
                'O endereço da loja ou de um redirecionamento aponta para uma rede privada ou reservada, que o monitor não pode acessar.',
                'Confira a URL, o DNS e os redirecionamentos para um endereço público da loja.',
            ],
            str_contains($text, 'redirecionamentos excedido'), str_contains($text, 'err_too_many_redirects') => [
                'Redirecionamentos demais',
                'A página ultrapassou a quantidade de redirecionamentos permitida pelo monitor.',
                'Confira se há um ciclo de redirecionamento entre HTTP/HTTPS, domínios ou páginas.',
            ],
            str_contains($text, 'ssl'), str_contains($text, 'certificate'), str_contains($text, 'err_cert_') => [
                'Não foi possível validar a conexão HTTPS',
                'O monitor encontrou um problema ao estabelecer ou validar a conexão segura com o destino.',
                'Confira a validade do certificado, o domínio e a cadeia de certificados no servidor.',
            ],
            str_contains($text, 'connection refused'), str_contains($text, 'failed to connect'), str_contains($text, 'curl error 7'), str_contains($text, 'err_connection_') => [
                'Não foi possível conectar ao destino',
                'A conexão com o endereço solicitado falhou. O serviço pode estar indisponível ou bloqueando o monitor.',
                'Confira a disponibilidade do destino, a porta e as regras de firewall. Para verificações de navegador, confira também o serviço de diagnóstico.',
            ],
            str_contains($text, 'url publica http/https invalida') => [
                'Endereço da loja inválido para esta verificação',
                'O monitor precisa de uma URL pública HTTP ou HTTPS em uma porta permitida.',
                'Revise o endereço cadastrado da loja e o caminho configurado na verificação.',
            ],
            str_contains($text, 'fila'), str_contains($text, 'agendar a execucao') => [
                'Não foi possível iniciar a verificação',
                'A solicitação não pôde ser enviada para processamento.',
                'Confira a disponibilidade da fila e tente novamente.',
            ],
            default => [
                'Não foi possível concluir a verificação',
                'O monitor encontrou um erro antes de concluir todas as condições. Este registro não confirma uma falha da loja.',
                'Consulte a mensagem original nos detalhes técnicos para investigar a causa e tente novamente.',
            ],
        };

        $checksCache = collect($snapshot['assertions'] ?? [])->contains(fn ($assertion) => in_array($assertion['field'] ?? '', [
            'cache_status', 'headers.x-cache', 'headers.x-magento-cache-debug', 'headers.cf-cache-status',
            'headers.x-litespeed-cache', 'headers.x-proxy-cache', 'headers.x-cache-status', 'headers.cache-control', 'headers.age',
        ], true));

        return [
            'title' => $title,
            'explanation' => $explanation,
            'impact' => $checksCache
                ? 'Cache não verificado: esta execução não permite concluir se a resposta foi HIT ou MISS.'
                : 'Resultado inconclusivo: as condições desta verificação não foram concluídas.',
            'action' => $action,
        ];
    }

    public function cache(array $observed): array
    {
        return match ($observed['cache_status'] ?? 'UNKNOWN') {
            'HIT' => [
                'title' => 'HIT — resposta atendida pelo cache',
                'explanation' => 'O cabeçalho de cache da resposta avaliada informou HIT.',
                'action' => null,
            ],
            'MISS' => [
                'title' => 'MISS — resposta não atendida pelo cache',
                'explanation' => 'O cabeçalho de cache da resposta avaliada informou MISS. Isso descreve o uso de cache nesta resposta e não indica, por si só, indisponibilidade da loja.',
                'action' => 'Confira as regras de cache da página no servidor ou CDN. Se a verificação usa aquecimento, o MISS foi identificado na última resposta, após as tentativas configuradas.',
            ],
            default => [
                'title' => 'Cache não identificado',
                'explanation' => empty($observed['cache_header'])
                    ? 'Nenhum dos cabeçalhos de cache reconhecidos pelo monitor foi encontrado. Isso não significa MISS nem comprova ausência de cache.'
                    : 'O cabeçalho encontrado não informou HIT ou MISS em um formato reconhecido pelo monitor. Isso não significa MISS.',
                'action' => 'Confira quais cabeçalhos o servidor ou CDN expõe e ajuste a condição da verificação, se necessário.',
            ],
        };
    }
}
