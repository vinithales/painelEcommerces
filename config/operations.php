<?php

return [
    'queue_connection' => env('OPERATIONS_QUEUE_CONNECTION', 'database'),
    'max_response_bytes' => 100 * 1024 * 1024, 'log_bytes' => 65536, 'log_max_bytes' => 262144,
];
