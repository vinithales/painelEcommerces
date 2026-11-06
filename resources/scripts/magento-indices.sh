#!/bin/sh
LIMITE_MINUTOS="${LIMITE_MINUTOS:-60}"
MAGENTO_ROOT=""

for pasta in "$PWD" "$PWD/public_html" "$HOME/public_html"; do
    if [ -r "$pasta/app/bootstrap.php" ]; then
        MAGENTO_ROOT="$pasta"
        break
    fi
done
if [ -z "$MAGENTO_ROOT" ]; then
    printf 'Magento não localizado. Usuário: %s | PWD: %s | HOME: %s\n' "$(id -un)" "$PWD" "$HOME" >&2
    ls -A | head -n 30 >&2
    exit 2
fi
cd "$MAGENTO_ROOT" || exit 2
printf 'Magento localizado em %s\n' "$MAGENTO_ROOT"
if ! command -v php >/dev/null 2>&1; then
    echo "PHP CLI indisponível para o usuário SSH." >&2
    exit 2
fi

export LIMITE_MINUTOS
exec php <<'PHP'
<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;

$limit = filter_var(getenv('LIMITE_MINUTOS'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($limit === false) {
    fwrite(STDERR, "LIMITE_MINUTOS deve ser um inteiro positivo.\n");
    exit(2);
}

try {
    require 'app/bootstrap.php';
    $resources = Bootstrap::create(BP, $_SERVER)->getObjectManager()->get(ResourceConnection::class);
    $db = $resources->getConnection();
    $alerts = 0;
    foreach (['indexer_state' => 'indexer_id', 'mview_state' => 'view_id'] as $table => $id) {
        $physicalTable = $db->quoteIdentifier($resources->getTableName($table));
        $sql = "SELECT $id AS id, status, TIMESTAMPDIFF(SECOND, updated, NOW()) AS age_seconds FROM $physicalTable";
        if ($table === 'mview_state') {
            $sql .= " WHERE mode = 'enabled'";
        }
        $rows = $db->fetchAll($sql." ORDER BY $id");
        if ($table === 'indexer_state' && !$rows) {
            throw new RuntimeException('Nenhum indexador encontrado.');
        }
        $group = $table === 'indexer_state' ? 'Índice' : 'Agendamento';
        foreach ($rows as $row) {
            $detail = $row['status'];
            if ($detail === 'suspended') {
                printf("[SUSPENSO] %s %s\n", $group, $row['id']);
                $alerts++;
                continue;
            }
            if ($row['status'] === 'working') {
                if (!is_numeric($row['age_seconds']) || $row['age_seconds'] < 0) {
                    throw new RuntimeException('Data de atualização inválida.');
                }
                $minutes = intdiv((int) $row['age_seconds'], 60);
                $detail = "em processamento, última atualização há $minutes min";
                if ($minutes >= $limit) {
                    $detail = 'POSSÍVEL TRAVAMENTO — '.$detail;
                    $alerts++;
                }
            }
            printf("%s %s: %s\n", $group, $row['id'], $detail);
        }
    }
    printf("Alertas de suspensão ou possível travamento: %d (limite: %d min).\n", $alerts, $limit);
    exit($alerts ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, "Diagnóstico inconclusivo. Confira o bootstrap do Magento, o acesso de leitura ao banco e os estados de indexação.\n");
    exit(2);
}
PHP
