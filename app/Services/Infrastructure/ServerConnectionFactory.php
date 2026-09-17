<?php

namespace App\Services\Infrastructure;

use App\Models\Store;
use App\Services\Checks\PublicHttpClient;

class ServerConnectionFactory
{
    public function make(Store $store): ServerConnectionInterface
    {
        $connection = $store->serverConnection;
        if (! $connection) {
            throw new \RuntimeException('Servidor não configurado.');
        }
        if ($connection->driver !== 'agent') {
            throw new \RuntimeException('Driver não suportado.');
        }

        return new AgentServerConnection($connection, app(PublicHttpClient::class));
    }
}
