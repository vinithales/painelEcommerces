#!/usr/bin/env php
<?php

// Substitute only the SSH client boundary. Never contacts an external server.
$capture = getenv('SSH_TEST_CAPTURE');
file_put_contents($capture, json_encode([
    'arguments' => array_slice($argv, 1),
    'script' => stream_get_contents(STDIN),
    'askpass' => getenv('SSH_ASKPASS'),
    'askpass_required' => getenv('SSH_ASKPASS_REQUIRE'),
    'password_matches' => shell_exec(escapeshellarg(getenv('SSH_ASKPASS'))) === " test-password \n",
]));
fwrite(STDOUT, "indices-ok\n");
fwrite(STDERR, "diagnostic-output\n");
exit((int) getenv('SSH_TEST_EXIT'));
