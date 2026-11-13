<?php

namespace App\Livewire\Projects;

use App\Jobs\CrawlSite;
use App\Livewire\Concerns\ForProject;
use App\Models\Crawl;
use App\Support\AuditIssues;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Audit extends Component
{
    use ForProject, WithPagination;

    public int $maxPages = 500;

    #[Url]
    public ?int $crawl = null;

    #[Url]
    public string $issue = '';

    public function updatedIssue(): void
    {
        $this->resetPage();
    }

    public function start(): void
    {
        $this->validate(['maxPages' => 'required|integer|min:1|max:10000'], attributes: ['maxPages' => 'nombre de pages']);

        if ($this->project->crawls()->whereIn('status', ['pending', 'running'])->exists()) {
            session()->flash('error', 'Un audit est déjà en cours pour ce site.');

            return;
        }

        $crawl = $this->project->crawls()->create(['max_pages' => $this->maxPages]);
        CrawlSite::dispatch($crawl);
        $this->crawl = $crawl->id;
        $this->issue = '';
    }

    public function render()
    {
        $crawls = $this->project->crawls()->latest('id')->limit(20)->get();
        /** @var ?Crawl $current */
        $current = $this->crawl ? $crawls->firstWhere('id', $this->crawl) : $crawls->first();

        $summary = [];
        $pages = null;
        $previous = null;

        if ($current && $current->status === 'done') {
            $all = $current->pages()->get(['id', 'issues']);
            foreach ($all as $page) {
                foreach ($page->issues ?? [] as $code) {
                    $summary[$code] = ($summary[$code] ?? 0) + 1;
                }
            }
            uksort($summary, function ($a, $b) use ($summary) {
                $order = [AuditIssues::ERROR => 0, AuditIssues::WARNING => 1, AuditIssues::NOTICE => 2];

                return [$order[AuditIssues::severity($a)], -$summary[$a]] <=> [$order[AuditIssues::severity($b)], -$summary[$b]];
            });

            $pages = $current->pages()
                ->when($this->issue, fn ($q) => $q->whereIn('id', $all->filter(fn ($p) => in_array($this->issue, $p->issues ?? []))->pluck('id')))
                ->orderBy('depth')->orderBy('url')
                ->paginate(50);

            $previous = $this->project->crawls()->where('status', 'done')->where('id', '<', $current->id)->latest('id')->first();
        }

        return view('livewire.projects.audit', [
            'crawls' => $crawls,
            'current' => $current,
            'summary' => $summary,
            'pages' => $pages,
            'previous' => $previous,
            'running' => $current && in_array($current->status, ['pending', 'running']),
        ])->title('Audit · '.$this->project->name);
    }
}
