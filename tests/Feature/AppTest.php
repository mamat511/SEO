<?php

namespace Tests\Feature;

use App\Jobs\CrawlSite;
use App\Livewire\Dashboard;
use App\Livewire\Projects;
use App\Models\Crawl;
use App\Models\DailyMetric;
use App\Models\Keyword;
use App\Models\KeywordMetric;
use App\Models\Project;
use App\Models\User;
use App\Services\BacklinkCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AppTest extends TestCase
{
    use RefreshDatabase;

    private function project(?User $user = null, array $attrs = []): Project
    {
        $user ??= User::factory()->create();

        return $user->projects()->create($attrs + ['name' => 'Exemple', 'url' => 'https://exemple.test/']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Se connecter');
    }

    public function test_user_can_add_several_sites_without_search_console(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['site-a.fr', 'https://site-b.fr/blog/'] as $i => $url) {
            Livewire::test(Dashboard::class)
                ->call('openForm')
                ->set('name', "Site $i")
                ->set('url', $url)
                ->call('create')
                ->assertHasNoErrors();
        }

        $this->assertSame(['https://site-a.fr', 'https://site-b.fr/blog/'], $user->projects()->orderBy('id')->pluck('url')->all());
        $this->get('/')->assertOk()->assertSee('Site 0')->assertSee('Site 1');
    }

    public function test_project_pages_render_with_data(): void
    {
        $project = $this->project();
        $this->actingAs($project->user);

        foreach (range(1, 60) as $d) {
            DailyMetric::create(['project_id' => $project->id, 'date' => now()->subDays($d + 2)->toDateString(), 'clicks' => 10 + $d, 'impressions' => 200, 'ctr' => .05, 'position' => 8.2]);
        }
        $kw = Keyword::create(['project_id' => $project->id, 'query' => 'agence seo']);
        foreach (range(1, 20) as $d) {
            KeywordMetric::create(['keyword_id' => $kw->id, 'date' => now()->subDays($d + 2)->toDateString(), 'position' => 4 + $d / 10, 'clicks' => 3, 'impressions' => 40, 'top_page' => $d === 1 ? 'https://exemple.test/seo' : null]);
        }

        foreach (['overview' => 'Trafic organique', 'rankings' => 'agence seo', 'backlinks' => 'Importer un export', 'audit' => 'Aucun audit', 'settings' => 'Supprimer le projet'] as $route => $text) {
            $this->get(route("projects.$route", $project))->assertOk()->assertSee($text);
        }

        Livewire::test(Projects\Rankings::class, ['project' => $project])
            ->set('selected', $kw->id)
            ->assertSee('Historique : agence seo');
    }

    public function test_other_users_project_is_not_accessible(): void
    {
        $project = $this->project();
        $this->actingAs(User::factory()->create())->get(route('projects.overview', $project))->assertNotFound();
    }

    public function test_backlinks_import_and_diff(): void
    {
        $project = $this->project();
        $this->actingAs($project->user);

        $first = "\xEF\xBB\xBFSite,Pages avec lien,Pages cibles\nexemple-a.com,\"1 200\",3\nexemple-b.fr,5,1\n";
        $second = "Site;Pages avec lien;Pages cibles\nexemple-a.com;1300;3\nexemple-c.org;2;1\n";

        $c = Livewire::test(Projects\Backlinks::class, ['project' => $project]);
        $c->set('file', UploadedFile::fake()->createWithContent('liens1.csv', $first))->call('import')->assertHasNoErrors();
        $this->travel(1)->days();
        $c->set('file', UploadedFile::fake()->createWithContent('liens2.csv', $second))->call('import')->assertHasNoErrors();

        $latest = $project->backlinkImports()->latest('id')->first();
        $this->assertSame('domains', $latest->kind);
        $this->assertSame(1300, $latest->backlinks()->where('source', 'exemple-a.com')->value('linking_pages'));

        $diff = Projects\Backlinks::diff($latest);
        $this->assertSame(['exemple-c.org'], $diff['new']);
        $this->assertSame(['exemple-b.fr'], $diff['lost']);

        $c->set('filter', 'lost')->assertSee('exemple-b.fr')->assertDontSee('exemple-c.org');
    }

    public function test_links_csv_is_detected(): void
    {
        $project = $this->project();
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "Page avec lien,Dernière exploration\nhttps://www.blog.fr/article,2026-10-02\nhttps://forum.net/t/1,2026-09-15\n");

        $import = (new BacklinkCsvImporter())->import($project, $path, 'liens.csv');

        $this->assertSame('links', $import->kind);
        $this->assertSame(2, $import->rows_count);
        $link = $import->backlinks()->where('source', 'https://www.blog.fr/article')->first();
        $this->assertSame('blog.fr', $link->domain);
        $this->assertSame('2026-10-02', $link->last_crawled->toDateString());
    }

    public function test_crawler_detects_issues(): void
    {
        $project = $this->project();
        $long = str_repeat('mot ', 300);

        Http::fake([
            'exemple.test/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\n"),
            'exemple.test/' => Http::response("<html><head><title>Accueil du site exemple pour tester</title><meta name=\"description\" content=\"Desc\"></head><body><h1>Accueil</h1><p>$long</p><a href=\"/a\">A</a><a href=\"b\">B</a><a href=\"/admin/x\">admin</a><a href=\"/old\">old</a><a href=\"https://ailleurs.test/\">ext</a><a href=\"mailto:x@y.z\">mail</a></body></html>", 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'exemple.test/a' => Http::response('<html><head><title>Accueil du site exemple pour tester</title></head><body><h1>A</h1><h1>A2</h1><a href="/manquante">x</a></body></html>', 200, ['Content-Type' => 'text/html']),
            'exemple.test/b' => Http::response('<html><head><title>B</title><meta name="robots" content="noindex"></head><body><p>court</p></body></html>', 200, ['Content-Type' => 'text/html']),
            'exemple.test/old' => Http::response('', 301, ['Location' => '/a']),
            'exemple.test/manquante' => Http::response('Not found', 404, ['Content-Type' => 'text/html']),
            '*' => Http::response('unexpected', 500),
        ]);

        $crawl = $project->crawls()->create(['max_pages' => 50]);
        (new CrawlSite($crawl))->handle();
        $crawl->refresh();

        $pages = $crawl->pages()->get()->keyBy(fn ($p) => preg_replace('#^https://exemple.test#', '', $p->url));

        $this->assertSame('done', $crawl->status);
        $this->assertEqualsCanonicalizing(['/', '/a', '/b', '/old', '/manquante'], $pages->keys()->all());
        $this->assertContains('meta_missing', $pages['/a']->issues);
        $this->assertContains('h1_multiple', $pages['/a']->issues);
        $this->assertContains('title_duplicate', $pages['/a']->issues);
        $this->assertContains('title_duplicate', $pages['/']->issues);
        $this->assertContains('links_to_broken', $pages['/a']->issues);
        $this->assertContains('noindex', $pages['/b']->issues);
        $this->assertContains('title_too_short', $pages['/b']->issues);
        $this->assertContains('redirect', $pages['/old']->issues);
        $this->assertContains('http_4xx', $pages['/manquante']->issues);
        $this->assertNotContains('thin_content', $pages['/']->issues);
        // 2 pages avec erreur (/a liens cassés, /manquante 404) sur 5.
        $this->assertSame(60, $crawl->health_score);

        $this->actingAs($project->user)->get(route('projects.audit', $project))->assertOk()->assertSee('Plusieurs H1');
    }
}
