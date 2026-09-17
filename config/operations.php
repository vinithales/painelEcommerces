<?php

return [
    'recipients' => array_values(array_filter(array_map('trim', explode(',', env('CHECK_ALERT_RECIPIENTS', ''))))),
    'queue_connection' => env('OPERATIONS_QUEUE_CONNECTION', 'database'),
    'max_response_bytes' => 1048576, 'log_bytes' => 65536, 'log_max_bytes' => 262144, 'retention_days' => 90,
];
