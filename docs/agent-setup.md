# Agente Magento somente leitura

O transporte implementado é um agente HTTPS autenticado. SSH e APIs de provedores podem implementar `ServerConnectionInterface` futuramente.

Copie estes arquivos preservando a estrutura, fora da raiz pública do Magento:

```text
bring-agent/
  agent/bring-agent.php
  agent/LogReader.php
  app/Support/Redactor.php
```

Configure uma localização HTTPS dedicada no servidor web para executar apenas `agent/bring-agent.php` via PHP-FPM. Preserve o header `Authorization`, restrinja a origem ao servidor do painel e não exponha os demais arquivos. O agente necessita PHP 8.1+, PDO MySQL e acesso de leitura aos logs.

Variáveis do processo PHP-FPM:

| Variável | Uso |
|---|---|
| `BRING_AGENT_TOKEN` | Segredo aleatório de pelo menos 32 caracteres |
| `MAGENTO_ROOT` | Caminho absoluto da instalação Magento |
| `BRING_DB_DSN` | DSN PDO MySQL para o diagnóstico de cron |
| `BRING_DB_USER` / `BRING_DB_PASSWORD` | Usuário com SELECT exclusivamente em `cron_schedule` |
| `MAGENTO_TABLE_PREFIX` | Prefixo de tabelas, se houver |

No painel, um administrador cadastra a URL HTTPS completa do script e o token nas configurações de servidor da loja. O token é criptografado e nunca reapresentado no formulário. A conexão exige endereço público, certificado válido e portas 80/443; redes privadas não são aceitas pelo cliente HTTP atual.

Operações GET permitidas: `status`, `cron` e `logs`. Não há execução de shell. O status informa conectividade, ocupação do disco e flag de manutenção. Cron consulta última execução, pendentes, atrasados, missed/error nas últimas 24 horas e execuções presas. Datas de cron são interpretadas em UTC.

Logs permitidos: `system.log`, `exception.log`, `debug.log`, `cron.log`. A leitura usa cursor e trechos limitados, bloqueia traversal e links simbólicos, e remove segredos conhecidos. O padrão é 64 KiB por trecho, com teto de 256 KiB. Falhas de infraestrutura não impedem consultas pela API da loja.

Nenhum agente é instalado automaticamente em lojas externas. Após implantação, use Diagnóstico para testar a conexão e criar um check `MAGENTO_CRON` com os limites adequados à loja.
