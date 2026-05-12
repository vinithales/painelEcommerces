<?php

namespace Tests\Feature\Checks;

use App\Models\Store;
use App\Services\IntegrationCheckers\Checks\RobotsTxtCheck;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RobotsTxtCheckTest extends TestCase
{
    private function makeStore(string $url = 'https://example.com'): Store
    {
        return new Store(['store_url' => $url]);
    }

    // --- key / supports ---

    public function test_key_returns_robots_txt(): void
    {
        $this->assertSame('robots_txt', (new RobotsTxtCheck())->key());
    }

    public function test_supports_returns_true_when_store_url_is_set(): void
    {
        $this->assertTrue((new RobotsTxtCheck())->supports($this->makeStore('https://example.com')));
    }

    public function test_supports_returns_false_when_store_url_is_empty(): void
    {
        $this->assertFalse((new RobotsTxtCheck())->supports($this->makeStore('')));
    }

    // --- 200 OK ---

    public function test_returns_ok_when_robots_txt_found(): void
    {
        Http::fake(['https://example.com/robots.txt' => Http::response('User-agent: *', 200)]);

        $result = (new RobotsTxtCheck())->check($this->makeStore());

        $this->assertSame('ok', $result['status']);
        $this->assertNull($result['error']);
    }

    // --- 404 ---

    public function test_returns_missing_when_robots_txt_not_found(): void
    {
        Http::fake(['https://example.com/robots.txt' => Http::response('', 404)]);

        $result = (new RobotsTxtCheck())->check($this->makeStore());

        $this->assertSame('missing', $result['status']);
        $this->assertSame('robots.txt not found', $result['error']);
    }

    // --- other error ---

    public function test_returns_failed_when_server_error(): void
    {
        Http::fake(['https://example.com/robots.txt' => Http::response('', 500)]);

        $result = (new RobotsTxtCheck())->check($this->makeStore());

        $this->assertSame('failed', $result['status']);
        $this->assertSame('HTTP 500', $result['error']);
    }

    public function test_returns_failed_when_redirect_or_other_non_success_status(): void
    {
        Http::fake(['https://example.com/robots.txt' => Http::response('', 403)]);

        $result = (new RobotsTxtCheck())->check($this->makeStore());

        $this->assertSame('failed', $result['status']);
        $this->assertSame('HTTP 403', $result['error']);
    }

    // --- trailing slash in store URL is normalized ---

    public function test_appends_robots_txt_after_stripping_trailing_slash(): void
    {
        Http::fake(['https://example.com/robots.txt' => Http::response('', 200)]);

        $store = $this->makeStore('https://example.com/');
        $result = (new RobotsTxtCheck())->check($store);

        $this->assertSame('ok', $result['status']);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/robots.txt');
    }
}
