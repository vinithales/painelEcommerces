<?php

namespace Tests\Unit;

use App\Services\Checks\CheckConfiguration;
use App\Services\Checks\MagentoCronCheck;
use App\Support\Redactor;
use BringAgent\LogReader;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    public function test_cron_requires_recent_execution_and_detects_stuck_jobs(): void
    {
        $checker = app(MagentoCronCheck::class);
        $config = CheckConfiguration::defaults('MAGENTO_CRON');
        $metrics = ['last_execution' => now()->toDateTimeString(), 'pending' => 1, 'missed' => 0, 'error' => 0, 'stuck_running' => 0, 'overdue' => 0];
        $this->assertSame('OK', $checker->evaluate($metrics, $config)->status);
        $this->assertSame('CRITICAL', $checker->evaluate(array_replace($metrics, ['last_execution' => now()->subHour()->toDateTimeString()]), $config)->status);
        $this->assertSame('CRITICAL', $checker->evaluate(array_replace($metrics, ['stuck_running' => 1]), $config)->status);
        $this->assertSame('CRITICAL', $checker->evaluate(array_replace($metrics, ['pending' => 101]), $config)->status);
    }

    public function test_log_allowlist_bounds_cursor_and_redaction(): void
    {
        require_once base_path('agent/LogReader.php');
        $root = sys_get_temp_dir().'/bring-test-'.bin2hex(random_bytes(5));
        mkdir($root.'/var/log', 0777, true);
        try {
            file_put_contents($root.'/var/log/system.log', str_repeat("safe line\n", 1000)."password=secret-value\n");
            $reader = new LogReader($root);
            $tail = $reader->read('system.log', null, 1024);
            $this->assertLessThanOrEqual(1024, strlen($tail['text']));
            $this->assertGreaterThan(0, $tail['cursor']);
            $this->assertStringNotContainsString('secret-value', $tail['text']);
            $older = $reader->read('system.log', $tail['cursor'], 1024);
            $this->assertLessThan($tail['cursor'], $older['cursor']);
            $this->expectException(\InvalidArgumentException::class);
            $reader->read('../../app/etc/env.php');
        } finally {
            unlink($root.'/var/log/system.log');
            rmdir($root.'/var/log');
            rmdir($root.'/var');
            rmdir($root);
        }
    }

    public function test_redaction_covers_headers_tokens_and_private_keys(): void
    {
        $text = Redactor::clean("Authorization: Bearer topsecret\npassword=secret\n-----BEGIN PRIVATE KEY-----\nsecret\n-----END PRIVATE KEY-----");
        $this->assertStringNotContainsString('topsecret', $text);
        $this->assertStringNotContainsString('=secret', $text);
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $text);
    }
}
