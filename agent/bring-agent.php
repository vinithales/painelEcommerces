<?php

/**
 * Deploy outside the Magento public tree and map a dedicated HTTPS location to it.
 * Configure BRING_AGENT_TOKEN, MAGENTO_ROOT; optional BRING_DB_DSN/USER/PASSWORD
 * MUST use a read-only DB user with SELECT permission on cron_schedule only.
 */
declare(strict_types=1);
use App\Support\Redactor;
use BringAgent\LogReader;

require_once __DIR__.'/../app/Support/Redactor.php';
require_once __DIR__.'/LogReader.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
$token = getenv('BRING_AGENT_TOKEN') ?: '';
if (strlen($token) < 32 || ! hash_equals('Bearer '.$token, $_SERVER['HTTP_AUTHORIZATION'] ?? '')) {
    http_response_code(401);
    echo '{"error":"Unauthorized"}';
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    exit;
}
try {
    $root = realpath(getenv('MAGENTO_ROOT') ?: '');
    if (! $root || ! is_file($root.'/app/etc/env.php')) {
        throw new RuntimeException('Magento root unavailable.');
    }
    $operation = $_GET['operation'] ?? '';
    if ($operation === 'logs') {
        $reader = new LogReader($root);
        $result = $reader->read((string) ($_GET['file'] ?? ''), isset($_GET['cursor']) ? (int) $_GET['cursor'] : null, (int) ($_GET['bytes'] ?? 65536));
    } elseif ($operation === 'cron') {
        $dsn = getenv('BRING_DB_DSN');
        if (! $dsn) {
            throw new RuntimeException('Read-only cron database not configured.');
        }
        $db = new PDO($dsn, getenv('BRING_DB_USER') ?: '', getenv('BRING_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $prefix = getenv('MAGENTO_TABLE_PREFIX') ?: '';
        if (! preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) {
            throw new RuntimeException('Invalid table prefix.');
        }
        $minutes = max(1, min(1440, (int) ($_GET['running_minutes'] ?? 30)));
        $table = $prefix.'cron_schedule';
        $sql = "SELECT MAX(executed_at) AS last_execution,
   SUM(status='pending') AS pending,
   SUM(status='missed' AND scheduled_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY) AS missed,
   SUM(status='error' AND scheduled_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY) AS error,
   SUM(status='running' AND executed_at < UTC_TIMESTAMP() - INTERVAL {$minutes} MINUTE) AS stuck_running,
   SUM(status='pending' AND scheduled_at < UTC_TIMESTAMP()) AS overdue FROM {$table}";
        $result = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        foreach (['pending', 'missed', 'error', 'stuck_running', 'overdue'] as $key) {
            $result[$key] = (int) $result[$key];
        }
    } elseif ($operation === 'status') {
        $free = disk_free_space($root);
        $total = disk_total_space($root);
        $result = ['available' => true, 'disk_used_percent' => $total ? round(100 * (1 - $free / $total), 2) : null, 'maintenance' => is_file($root.'/var/.maintenance.flag'), 'checked_at' => gmdate('c')];
    } else {
        http_response_code(400);
        echo '{"error":"Operation not allowed"}';
        exit;
    }
    echo json_encode(Redactor::clean($result), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(503);
    echo '{"error":"Agent operation unavailable; inspect restricted server diagnostics"}';
}
