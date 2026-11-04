<?php

return [
    'browser_url' => env('BROWSER_CHECK_URL', 'http://browser:3000'),
    'browser_token' => env('BROWSER_CHECK_TOKEN', env('APP_KEY')),
];
