# Cache e console por loja

O comando abaixo cadastra duas definições e as associa a todas as lojas existentes. Pode ser repetido: preserva definições e configurações já cadastradas, sem duplicar associações. Novas associações têm intervalo de 24 horas. Execute novamente após cadastrar novas lojas.

```sh
docker compose up -d --build browser
docker compose up -d app queue scheduler
docker compose exec app php artisan checks:install-store-diagnostics --run
```

`--run` enfileira uma execução inicial de cada verificação. Sem essa opção, apenas cadastra. As execuções seguintes usam o agendador existente. O painel permite executar novamente em uma, várias ou todas as lojas habilitadas, inclusive para o executor `browser`. Com `--run --executor=http` ou `--run --executor=browser`, limita a execucao inicial ao tipo informado.

## Cache

Sao tres GETs anonimos consecutivos a pagina inicial, seguindo redirecionamentos. Cada amostra registra status, URL final e cabecalhos. A ultima resposta precisa retornar HTTP 200 e `cache_status` igual a `HIT`. A deteccao escolhe o primeiro cabecalho presente nesta ordem: X-Cache, X-Magento-Cache-Debug, CF-Cache-Status, X-LiteSpeed-Cache, X-Proxy-Cache, X-Cache-Status. A origem fica em `cache_header`. Valores contendo MISS prevalecem sobre HIT; valores sem HIT/MISS ou sem cabecalho reconhecido produzem UNKNOWN e reprovam. Um primeiro MISS seguido de HIT e aceito. UNKNOWN indica falta de evidencia reconhecida, nao prova ausencia de qualquer camada de cache.

Campos `headers.<nome>` aceitam qualquer nome de cabeçalho via definição; o painel oferece X-Cache, CF-Cache-Status, X-LiteSpeed-Cache, Cache-Control e Age. Os nomes são normalizados para minúsculas. Cabeçalho ausente reprova qualquer comparação, inclusive `not_equals` e `not_contains`. Operadores textuais: igualdade, diferença, contém/não contém e variantes que ignoram maiúsculas. As condições continuam combinadas por E.

Configuração HTTP opcional: `samples` entre 1 e 3 (padrao 1), apenas para GET/HEAD quando maior que 1. O resultado de cada condição se refere à última resposta. Cookies de resposta não são persistidos no histórico nem reaproveitados nos acessos de aquecimento.

## Console

Chromium abre a página inicial, aguarda o evento load (até 30 segundos) e observa mais 5 segundos. O sucesso exige HTTP 200, nenhum `console.error` e nenhuma exceção JavaScript não tratada (`pageerror`). Guarda mensagens, arquivo/linha do console, mensagem/stack das exceções, contagens e requisições bloqueadas em `check_executions.result.observed`, vinculado à loja pela associação. Falha de navegação é erro de execução, preservando mensagens já capturadas.

Consulte **Histórico de verificações → Detalhes** na loja para ler os erros e os cabeçalhos. Não há cliques, login, navegação de checkout nem varredura de todas as páginas. Erros fora da janela de observação não são capturados. Service workers e WebSockets ficam bloqueados; solicitações HTTP iniciadas pelos scripts da página são processadas normalmente. Bloqueios e limites do monitor aparecem no historico e podem afetar a pagina observada. Quando ha bloqueio por politica ou limites, o resultado e marcado como observacao incompleta, preservando as mensagens coletadas.

Há limite de 100 registros por tipo (contagens incluem todos), mensagens de até 8 mil caracteres e stacks de até 16 mil. O resultado informa truncamento. Recursos interceptados tem limite de 5 MB, ate 1000 requisicoes/30 MB por página. HTML observado é limitado a 1 MB. Não grava cookies nos cabeçalhos do histórico.

O serviço `browser` fica na rede interna do Compose, sem porta publicada, autenticado com `BROWSER_CHECK_TOKEN` (APP_KEY por padrão). A aplicação usa `BROWSER_CHECK_URL`, padrão `http://browser:3000`. Fora de Docker, configure ambos para um serviço equivalente. Todos os destinos HTTP, incluindo subrecursos e redirecionamentos, têm DNS validado e endereço público fixado na conexão; destinos privados/reservados são bloqueados. Execuções de navegador são serializadas por lock compartilhado para não sobrecarregar o serviço. Múltiplos workers devem compartilhar o cache de locks, como já exigido pela aplicação.

Referência da captura e interceptação: [Playwright Page](https://playwright.dev/docs/api/class-page) e [Network](https://playwright.dev/docs/network).
