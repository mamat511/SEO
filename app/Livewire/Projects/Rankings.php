<?php

namespace App\Livewire\Projects;

use App\Jobs\SyncSearchConsole;
use App\Livewire\Concerns\ForProject;
use App\Models\Keyword;
use App\Services\SearchConsole;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class Rankings extends Component
{
    use ForProject;

    /** Un mot-clé par ligne. */
    public string $newKeywords = '';

    #[Url]
    public ?int $selected = null;

    #[Url]
    public string $sort = 'position';

    public array $suggestions = [];
    public ?string $suggestionsError = null;

    public function add(): void
    {
        $this->validate(['newKeywords' => 'required|string|max:20000'], attributes: ['newKeywords' => 'mots-clés']);

        $queries = collect(preg_split('/\R/', $this->newKeywords))
            ->map(fn ($q) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $q))))
            ->filter()
            ->unique()
            ->take(500);

        $created = 0;
        foreach ($queries as $query) {
            $keyword = $this->project->keywords()->firstOrCreate(['query' => mb_substr($query, 0, 255)]);
            if ($keyword->wasRecentlyCreated) {
                $created++;
                if ($this->project->gsc_property) {
                    SyncSearchConsole::dispatch($this->project, $keyword->id);
                }
            }
        }

        $this->reset('newKeywords');
        session()->flash('success', $created ? "$created mot(s)-clé(s) ajouté(s). L'historique est en cours d'import." : 'Ces mots-clés sont déjà suivis.');
    }

    public function track(string $query): void
    {
        $this->newKeywords = $query;
        $this->add();
        $this->suggestions = array_values(array_filter($this->suggestions, fn ($s) => $s['query'] !== $query));
    }

    public function remove(int $id): void
    {
        $this->project->keywords()->whereKey($id)->delete();
        if ($this->selected === $id) {
            $this->selected = null;
        }
    }

    /** Requêtes qui génèrent déjà des impressions mais ne sont pas encore suivies. */
    public function loadSuggestions(): void
    {
        $this->suggestionsError = null;
        if (! $this->project->gsc_property) {
            $this->suggestionsError = 'Associe une propriété Search Console à ce projet dans les réglages.';

            return;
        }

        try {
            $tracked = $this->project->keywords()->pluck('query')->flip();
            $end = now()->subDays(2)->toDateString();
            $start = now()->subDays(29)->toDateString();

            $this->suggestions = collect(SearchConsole::for(auth()->user())->query($this->project->gsc_property, $start, $end, ['query'], [], 1000))
                ->reject(fn ($r) => isset($tracked[$r['keys'][0]]))
                ->sortByDesc('impressions')
                ->take(50)
                ->map(fn ($r) => ['query' => $r['keys'][0], 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'position' => round($r['position'], 1)])
                ->values()
                ->all();
        } catch (Throwable $e) {
            $this->suggestionsError = $e->getMessage();
        }
    }

    public function render()
    {
        $rows = $this->project->keywords()->with(['metrics' => fn ($q) => $q->where('date', '>=', now()->subDays(40)->toDateString())->orderBy('date')])
            ->get()
            ->map(function (Keyword $k) {
                $m = $k->metrics;
                $last = $m->last();
                $weekAgo = $m->filter(fn ($x) => $last && $x->date->lte($last->date->copy()->subDays(7)))->last();
                $recent = $m->filter(fn ($x) => $last && $x->date->gt($last->date->copy()->subDays(28)));

                return [
                    'id' => $k->id,
                    'query' => $k->query,
                    'position' => $last?->position,
                    'change' => ($last && $weekAgo) ? $weekAgo->position - $last->position : null, // positif = progression
                    'clicks' => $recent->sum('clicks'),
                    'impressions' => $recent->sum('impressions'),
                    'page' => $m->whereNotNull('top_page')->last()?->top_page,
                    'date' => $last?->date,
                    'spark' => $m->pluck('position')->all(),
                ];
            });

        $rows = match ($this->sort) {
            'clicks' => $rows->sortByDesc('clicks'),
            'impressions' => $rows->sortByDesc('impressions'),
            'change' => $rows->sortByDesc(fn ($r) => $r['change'] ?? -INF),
            'query' => $rows->sortBy('query'),
            default => $rows->sortBy(fn ($r) => $r['position'] ?? INF),
        };

        $history = null;
        if ($this->selected && ($k = $this->project->keywords()->find($this->selected))) {
            $metrics = $k->metrics()->orderBy('date')->get();
            $history = [
                'query' => $k->query,
                'labels' => $metrics->map(fn ($m) => $m->date->format('d/m/Y'))->all(),
                'position' => $metrics->map(fn ($m) => round($m->position, 1))->all(),
                'clicks' => $metrics->pluck('clicks')->all(),
            ];
        }

        return view('livewire.projects.rankings', ['rows' => $rows->values(), 'history' => $history])
            ->title('Positions · '.$this->project->name);
    }
}
