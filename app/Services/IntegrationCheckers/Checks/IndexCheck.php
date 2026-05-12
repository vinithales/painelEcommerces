<?php

namespace App\Services\IntegrationCheckers\Checks;

use App\Models\Store;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

class IndexCheck implements StoreCheck
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 10,
            'verify' => false,
        ]);
    }

    public function key(): string
    {
        return 'indexability';
    }

    public function supports(Store $store): bool
    {
        return filled($store->store_url);
    }

    public function check(Store $store): array
    {
        try {
            $response = $this->client->get($store->store_url);
            $html = (string) $response->getBody();

            $crawler = new Crawler($html);
            $metaRobots = $crawler->filter('meta')->reduce(
                fn (Crawler $node) => strtolower((string) $node->attr('name')) === 'robots'
            )->first();
            $directives = $this->extractDirectives(
                $metaRobots->count() > 0 ? (string) $metaRobots->attr('content') : null,
                $response->getHeaderLine('X-Robots-Tag')
            );

            return $this->mapStatus($directives);
        } catch (RequestException $exception) {
            return [
                'status' => 'failed',
                'error' => 'HTTP request failed: ' . $exception->getMessage(),
                'directives' => [],
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractDirectives(?string $metaRobotsContent, ?string $xRobotsTag): array
    {
        $values = array_filter([$metaRobotsContent, $xRobotsTag], fn (?string $value) => filled($value));

        $directives = [];

        foreach ($values as $value) {
            foreach (preg_split('/[,;]+/', strtolower($value)) ?: [] as $directive) {
                $normalized = trim($directive);

                if ($normalized === '') {
                    continue;
                }

                $directives[] = $normalized;
            }
        }

        return array_values(array_unique($directives));
    }

    /**
     * @param array<int, string> $directives
     * @return array{status: string, error: ?string, directives: array<int, string>}
     */
    private function mapStatus(array $directives): array
    {
        $hasNoindex = in_array('noindex', $directives, true);
        $hasIndex = in_array('index', $directives, true);
        $hasNofollow = in_array('nofollow', $directives, true);
        $hasFollow = in_array('follow', $directives, true);

        $indexState = $hasNoindex ? 'noindex' : ($hasIndex ? 'index' : 'default_index');
        $followState = $hasNofollow ? 'nofollow' : ($hasFollow ? 'follow' : 'default_follow');

        $status = match (true) {
            $indexState === 'noindex' && $followState === 'nofollow' => 'noindex_nofollow',
            $indexState === 'noindex' => 'noindex',
            $followState === 'nofollow' && $indexState === 'index' => 'index_nofollow',
            $followState === 'nofollow' => 'nofollow',
            $indexState === 'index' => 'index',
            default => 'indexable',
        };

        $messages = [];

        if ($hasNoindex) {
            $messages[] = 'Page contains noindex';
        }

        if ($hasNofollow) {
            $messages[] = 'Page contains nofollow';
        }

        foreach (['noarchive', 'nosnippet', 'noimageindex', 'max-snippet', 'max-image-preview', 'max-video-preview', 'unavailable_after'] as $directivePrefix) {
            foreach ($directives as $directive) {
                if ($directive === $directivePrefix || str_starts_with($directive, $directivePrefix . ':')) {
                    $messages[] = 'Directive present: ' . $directive;
                }
            }
        }

        return [
            'status' => $status,
            'error' => $messages === [] ? null : implode('; ', $messages),
            'directives' => $directives,
        ];
    }
}