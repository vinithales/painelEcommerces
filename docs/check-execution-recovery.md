# Recuperação de execuções interrompidas

O comando `php artisan checks:recover-abandoned` finaliza como `error` apenas
execuções `running`, sem `finished_at`, cujo `started_at` é anterior a cinco
minutos atrás. O Scheduler o executa a cada minuto; com o Scheduler ativo, a
recuperação normalmente ocorre entre cinco e seis minutos após o início.

Execuções `pending` nunca são recuperadas por idade: podem estar aguardando
outros Jobs. Registros `running` sem `started_at` também ficam fora da seleção;
`created_at` não substitui o início da execução.

A recuperação preserva o registro, a associação, o snapshot, o resultado parcial
e os horários originais. Define `status = error`, `outcome = null`, uma mensagem
explicativa e `finished_at`. A atualização contém os próprios critérios de
elegibilidade, evitando sobrescrever uma execução já finalizada. Rodar o comando
novamente não modifica registros recuperados.

## Limites da fila

- Verificações: timeout explícito de 120 segundos; lock expira em 150 segundos.
- Aquecimento de cache: timeout de 300 segundos.
- Conexões database, Redis e Beanstalkd: `retry_after` de 360 segundos, acima
  do maior timeout. Esse valor controla a reserva do Job, não o prazo de abandono.
- O prazo de abandono das verificações permanece em cinco minutos, acima de seu
  timeout de 120 segundos. Ele não se aplica aos Jobs de aquecimento de cache.

Após publicar essas mudanças, reinicie os workers para carregar o novo código
e a configuração da fila. Se houver cache de configuração, regenere-o antes
do reinício. O Scheduler também deve carregar o novo agendamento.
