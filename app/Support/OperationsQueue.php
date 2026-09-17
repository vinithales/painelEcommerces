<?php

namespace App\Support;

final class OperationsQueue
{
    public static function connection(): string
    {
        $connection = config('operations.queue_connection', 'database');
        if (! in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            throw new \RuntimeException('Operações exigem uma fila assíncrona configurada.');
        }

        return $connection;
    }
}
