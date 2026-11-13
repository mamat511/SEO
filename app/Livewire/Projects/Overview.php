<?php

namespace App\Livewire\Projects;

use App\Jobs\SyncSearchConsole;
use App\Livewire\Concerns\ForProject;
use Livewire\Attributes\Url;
use Livewire\Component;

class Overview extends Component
{
    use ForProject;

    #[Url]
    public int $days = 90;

    public function sync(): void
    {
        SyncSearchConsole::dispatch($this->project);
        session()->flash('success', 'Synchronisation lancée. Les données arrivent dans quelques instants.');
    }

    public function render()
    {
        $days = in_array($this->days, [28, 90, 180, 365, 480]) ? $this->days : 90;
        $project = $this->project;

        $series = $project->dailyMetrics()
            ->where('date', '>=', now()->subDays($days + 2)->toDateString())
            ->orderBy('date')
            ->get(['date', 'clicks', 'impressions', 'position']);

        $keywords = $project->keywords()->with(['metrics' => fn ($q) => $q->latest('date')->limit(1)])->get()
            ->map(fn ($k) => ['query' => $k->query, 'm' => $k->metrics->first()])
            ->filter(fn ($k) => $k['m'])
            ->sortBy(fn ($k) => $k['m']->position)
            ->take(10);

        $positions = [
            '1–3' => 0, '4–10' => 0, '11–20' => 0, '21–50' => 0, '51+' => 0,
        ];
        foreach ($project->keywords()->with(['metrics' => fn ($q) => $q->latest('date')->limit(1)])->get() as $k) {
            $p = $k->metrics->first()?->position;
            if ($p === null) {
                continue;
            }
            $bucket = match (true) {
                $p <= 3.5 => '1–3', $p <= 10.5 => '4–10', $p <= 20.5 => '11–20', $p <= 50.5 => '21–50', default => '51+',
            };
            $positions[$bucket]++;
        }

        $lastDomains = $project->backlinkImports()->where('kind', 'domains')->latest('id')->first();

        return view('livewire.projects.overview', [
            'summary' => $project->summary(28),
            'chart' => [
                'labels' => $series->map(fn ($r) => $r->date->format('d/m/Y'))->all(),
                'clicks' => $series->pluck('clicks')->all(),
                'impressions' => $series->pluck('impressions')->all(),
            ],
            'keywords' => $keywords,
            'positions' => $positions,
            'crawl' => $project->latestCrawl,
            'domains' => $lastDomains,
            'domainsDiff' => $lastDomains ? Backlinks::diff($lastDomains) : null,
        ])->title($project->name);
    }
}
