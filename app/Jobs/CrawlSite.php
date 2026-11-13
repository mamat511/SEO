<?php

namespace App\Jobs;

use App\Models\Crawl;
use App\Models\CrawlPage;
use App\Support\AuditIssues;
use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Parcourt le site d'un projet (même hôte uniquement), analyse chaque page HTML
 * et enregistre les problèmes SEO détectés.
 */
class CrawlSite implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 1;

    private const CONCURRENCY = 5;
    private const USER_AGENT = 'Mozilla/5.0 (compatible; SeoAuditBot/1.0)';

    private string $host;

    /** @var list<string> préfixes interdits par robots.txt */
    private array $disallowed = [];

    /** @var array<string, int> URL => profondeur, déjà vues */
    private array $seen = [];

    /** @var array<string, list<string>> page => liens internes trouvés */
    private array $links = [];

    public function __construct(public Crawl $crawl)
    {
    }

    public function handle(): void
    {
        $crawl = $this->crawl;
        $crawl->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        $start = $this->normalize($crawl->project->url);
        $this->host = parse_url($start, PHP_URL_HOST);
        $this->loadRobots($start);

        $queue = [$start];
        $this->seen[$start] = 0;
        $count = 0;

        while ($queue && $count < $crawl->max_pages) {
            $batch = array_splice($queue, 0, min(self::CONCURRENCY, $crawl->max_pages - $count));
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($url) => $pool->as($url)
                    ->withUserAgent(self::USER_AGENT)
                    ->withoutRedirecting()
                    ->timeout(20)
                    ->get($url),
                $batch,
            ));

            foreach ($batch as $url) {
                foreach ($this->analyze($url, $responses[$url] ?? null) as $next) {
                    $queue[] = $next;
                }
                $count++;
            }

            $crawl->update(['pages_count' => $count]);
        }

        $this->finish();
    }

    public function failed(Throwable $e): void
    {
        $this->crawl->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
    }

    /** @return list<string> nouvelles URL internes à explorer */
    private function analyze(string $url, mixed $response): array
    {
        $depth = $this->seen[$url];
        $page = ['crawl_id' => $this->crawl->id, 'url' => $url, 'depth' => $depth, 'issues' => []];

        if (! $response instanceof Response) {
            CrawlPage::create(array_merge($page, ['issues' => ['unreachable']]));

            return [];
        }

        // Temps de transfert mesuré par cURL pour cette requête seule.
        $elapsed = (int) round(($response->handlerStats()['total_time'] ?? 0) * 1000);
        $page['response_ms'] = $elapsed;

        $status = $response->status();
        $page['status_code'] = $status;
        $issues = [];
        $found = [];

        if ($status >= 300 && $status < 400) {
            $target = $this->resolve($response->header('Location'), $url);
            $page['redirect_to'] = $target;
            $issues[] = 'redirect';
            if ($target && $this->isInternal($target)) {
                $found[] = $target;
            }
        } elseif ($status >= 500) {
            $issues[] = 'http_5xx';
        } elseif ($status >= 400) {
            $issues[] = 'http_4xx';
        } elseif (str_contains(strtolower($response->header('Content-Type')), 'text/html')) {
            [$data, $found, $htmlIssues] = $this->parseHtml($response->body(), $url, $response->header('X-Robots-Tag'));
            $page = array_merge($page, $data);
            $issues = array_merge($issues, $htmlIssues);
        } else {
            return []; // PDF, image… : on ne l'enregistre pas
        }

        if ($elapsed > 1500) {
            $issues[] = 'slow';
        }

        $page['issues'] = $issues;
        CrawlPage::create($page);

        $this->links[$url] = $found;
        $next = [];
        foreach (array_unique($found) as $link) {
            if (! isset($this->seen[$link]) && $this->allowed($link)) {
                $this->seen[$link] = $depth + 1;
                $next[] = $link;
            }
        }

        return $next;
    }

    /** @return array{0: array, 1: list<string>, 2: list<string>} */
    private function parseHtml(string $html, string $url, string $robotsHeader): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $title = trim($xpath->evaluate('string(//head/title)') ?: $xpath->evaluate('string(//title)'));
        $meta = trim($xpath->evaluate('string(//meta[translate(@name,"DESCRIPTON","descripton")="description"]/@content)'));
        $robots = strtolower($xpath->evaluate('string(//meta[translate(@name,"ROBTS","robts")="robots"]/@content)').' '.strtolower($robotsHeader));
        $canonical = trim($xpath->evaluate('string(//link[translate(@rel,"CANOICL","canoicl")="canonical"]/@href)'));
        $h1 = $xpath->query('//h1')->length;

        foreach ($xpath->query('//script|//style|//noscript') as $node) {
            $node->parentNode->removeChild($node);
        }
        $text = $xpath->evaluate('string(//body)');
        $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));

        $base = $xpath->evaluate('string(//base/@href)') ?: $url;
        $links = [];
        foreach ($xpath->query('//a[@href]') as $a) {
            if (str_contains(strtolower($a->getAttribute('rel')), 'nofollow')) {
                continue;
            }
            $link = $this->resolve($a->getAttribute('href'), $base);
            if ($link && $this->isInternal($link)) {
                $links[] = $link;
            }
        }

        $canonical = $canonical ? $this->resolve($canonical, $url) : null;
        $noindex = str_contains($robots, 'noindex');

        $issues = [];
        $len = mb_strlen($title);
        if ($title === '') {
            $issues[] = 'title_missing';
        } elseif ($len > 60) {
            $issues[] = 'title_too_long';
        } elseif ($len < 20) {
            $issues[] = 'title_too_short';
        }
        if ($meta === '') {
            $issues[] = 'meta_missing';
        } elseif (mb_strlen($meta) > 160) {
            $issues[] = 'meta_too_long';
        }
        if ($h1 === 0) {
            $issues[] = 'h1_missing';
        } elseif ($h1 > 1) {
            $issues[] = 'h1_multiple';
        }
        if ($words < 200) {
            $issues[] = 'thin_content';
        }
        if ($noindex) {
            $issues[] = 'noindex';
        }
        if ($canonical && $canonical !== $url) {
            $issues[] = 'canonical_other';
        }

        return [[
            'title' => mb_substr($title, 0, 1024) ?: null,
            'meta_description' => mb_substr($meta, 0, 2048) ?: null,
            'h1_count' => $h1,
            'canonical' => $canonical,
            'noindex' => $noindex,
            'word_count' => $words,
        ], $links, $issues];
    }

    /** Problèmes qui demandent une vue d'ensemble : doublons et liens cassés, puis score. */
    private function finish(): void
    {
        $pages = $this->crawl->pages()->get();
        $indexable = $pages->filter(fn ($p) => $p->status_code === 200 && ! $p->noindex);

        $dupTitles = $indexable->whereNotNull('title')->groupBy('title')->filter(fn ($g) => $g->count() > 1)->keys()->flip();
        $dupMetas = $indexable->whereNotNull('meta_description')->groupBy('meta_description')->filter(fn ($g) => $g->count() > 1)->keys()->flip();
        $broken = $pages->filter(fn ($p) => $p->status_code >= 400 || in_array('unreachable', $p->issues ?? []))->pluck('url')->flip();

        $issuesCount = 0;
        $withErrors = 0;

        foreach ($pages as $page) {
            $issues = $page->issues ?? [];
            if ($indexable->contains('id', $page->id)) {
                if (isset($dupTitles[$page->title])) {
                    $issues[] = 'title_duplicate';
                }
                if ($page->meta_description && isset($dupMetas[$page->meta_description])) {
                    $issues[] = 'meta_duplicate';
                }
            }
            foreach ($this->links[$page->url] ?? [] as $link) {
                if (isset($broken[$link])) {
                    $issues[] = 'links_to_broken';
                    break;
                }
            }

            if ($issues !== ($page->issues ?? [])) {
                $page->update(['issues' => $issues]);
            }

            $issuesCount += count($issues);
            if (collect($issues)->contains(fn ($i) => AuditIssues::severity($i) === AuditIssues::ERROR)) {
                $withErrors++;
            }
        }

        $this->crawl->update([
            'status' => 'done',
            'pages_count' => $pages->count(),
            'issues_count' => $issuesCount,
            // Part des pages sans erreur bloquante, comme un « score de santé ».
            'health_score' => $pages->count() ? (int) round(100 * ($pages->count() - $withErrors) / $pages->count()) : null,
            'finished_at' => now(),
        ]);
    }

    private function loadRobots(string $start): void
    {
        try {
            $robots = Http::withUserAgent(self::USER_AGENT)->timeout(10)->get(rtrim($start, '/').'/robots.txt');
        } catch (Throwable) {
            return;
        }
        if (! $robots->successful()) {
            return;
        }

        $applies = false;
        foreach (preg_split('/\R/', $robots->body()) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                $applies = trim($m[1]) === '*';
            } elseif ($applies && preg_match('/^disallow:\s*(\S+)/i', $line, $m)) {
                $this->disallowed[] = $m[1];
            }
        }
    }

    private function allowed(string $url): bool
    {
        $path = (parse_url($url, PHP_URL_PATH) ?: '/').(($q = parse_url($url, PHP_URL_QUERY)) ? "?$q" : '');
        foreach ($this->disallowed as $rule) {
            $pattern = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule, '#')).'#';
            if (preg_match($pattern, $path)) {
                return false;
            }
        }

        return true;
    }

    private function isInternal(string $url): bool
    {
        return parse_url($url, PHP_URL_HOST) === $this->host;
    }

    private function resolve(?string $href, string $base): ?string
    {
        $href = trim((string) $href);
        if ($href === '' || preg_match('#^(mailto|tel|javascript|data):#i', $href) || str_starts_with($href, '#')) {
            return null;
        }

        $b = parse_url($base);
        if (str_starts_with($href, '//')) {
            $href = $b['scheme'].':'.$href;
        } elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            if (! preg_match('#^https?://#i', $href)) {
                return null; // ftp:, sms:, etc.
            }
        } else {
            $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
            if (str_starts_with($href, '/')) {
                $href = $origin.$href;
            } else {
                $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
                $href = $origin.$dir.$href;
            }
        }

        return $this->normalize($href);
    }

    /** Supprime le fragment, résout les « ./ » et « ../ », met l'hôte en minuscules. */
    private function normalize(string $url): string
    {
        $p = parse_url(preg_replace('/#.*$/', '', $url));
        $segments = [];
        foreach (explode('/', $p['path'] ?? '/') as $seg) {
            if ($seg === '..') {
                array_pop($segments);
            } elseif ($seg !== '.') {
                $segments[] = $seg;
            }
        }
        $path = implode('/', $segments) ?: '/';
        if ($path[0] !== '/') {
            $path = '/'.$path;
        }

        return strtolower($p['scheme'] ?? 'https').'://'.strtolower($p['host'] ?? '')
            .(isset($p['port']) ? ':'.$p['port'] : '')
            .$path
            .(isset($p['query']) ? '?'.$p['query'] : '');
    }
}
