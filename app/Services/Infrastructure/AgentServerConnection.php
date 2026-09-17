<?php

namespace App\Services\Infrastructure;

use App\Models\ServerConnection;
use App\Services\Checks\PublicHttpClient;
use App\Support\Redactor;

class AgentServerConnection implements ServerConnectionInterface
{
    public const LOGS = ['system.log', 'exception.log', 'debug.log', 'cron.log'];

    public function __construct(private readonly ServerConnection $connection, private readonly PublicHttpClient $http) {}

    private function read(string $operation, array $query = []): array
    {
        if (parse_url($this->connection->endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new \RuntimeException('Agente exige HTTPS.');
        }
        $r = $this->http->fetch($this->connection->endpoint.'?'.http_build_query(['operation' => $operation] + $query), 'GET', 20, false, ['Authorization' => 'Bearer '.$this->connection->token]);
        if ($r['status'] !== 200) {
            throw new \RuntimeException('Agente indisponível: HTTP '.$r['status']);
        }
        $data = json_decode($r['body'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \RuntimeException('Resposta inválida do agente.');
        }

        return Redactor::clean($data);
    }

    public function logs(string $file, ?int $cursor = null, int $bytes = 65536): array
    {
        if (! in_array($file, self::LOGS, true)) {
            throw new \InvalidArgumentException('Log não autorizado.');
        }

        return $this->read('logs', array_filter(['file' => $file, 'cursor' => $cursor, 'bytes' => max(1024, min($bytes, config('operations.log_max_bytes')))], fn ($v) => $v !== null));
    }

    public function cron(int $runningMinutes): array
    {
        return $this->read('cron', ['running_minutes' => max(1, min(1440, $runningMinutes))]);
    }

    public function status(): array
    {
        return $this->read('status');
    }
}
