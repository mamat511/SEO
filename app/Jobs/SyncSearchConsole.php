<?php

namespace App\Jobs;

use App\Models\DailyMetric;
use App\Models\Keyword;
use App\Models\KeywordMetric;
use App\Models\Project;
use App\Services\SearchConsole;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Importe les totaux quotidiens du site et l'historique des mots-clés suivis.
 *
 * Premier passage : 16 mois (la durée conservée par Google).
 * Passages suivants : on reprend 4 jours avant la dernière date connue,
 * car les données des derniers jours sont encore révisées par Google.
 */
class SyncSearchConsole implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public Project $project, public ?int $keywordId = null)
    {
    }

    public function uniqueId(): string
    {
        return $this->project->id.'-'.($this->keywordId ?? 'all');
    }

    public function handle(): void
    {
        $project = $this->project;

        if (! $project->gsc_property) {
            return;
        }

        $gsc = SearchConsole::for($project->user);
        $end = now()->subDays(2)->toDateString(); // Google publie les données avec ~2 jours de retard
        $earliest = now()->subMonths(16)->addDay();

        if ($this->keywordId === null) {
            $this->syncTotals($gsc, $project, $this->startFrom($project->dailyMetrics()->max('date'), $earliest), $end);
        }

        $keywords = $project->keywords()
            ->when($this->keywordId, fn ($q) => $q->whereKey($this->keywordId))
            ->get();

        foreach ($keywords as $keyword) {
            $this->syncKeyword($gsc, $project, $keyword, $this->startFrom($keyword->metrics()->max('date'), $earliest), $end);
        }

        if ($this->keywordId === null) {
            $project->update(['gsc_synced_at' => now()]);
        }
    }

    private function startFrom(?string $lastDate, Carbon $earliest): string
    {
        return $lastDate
            ? Carbon::parse($lastDate)->subDays(4)->max($earliest)->toDateString()
            : $earliest->toDateString();
    }

    private function syncTotals(SearchConsole $gsc, Project $project, string $start, string $end): void
    {
        $rows = [];

        foreach ($gsc->query($project->gsc_property, $start, $end, ['date']) as $row) {
            $rows[] = [
                'project_id' => $project->id,
                'date' => $row['keys'][0],
                'clicks' => (int) $row['clicks'],
                'impressions' => (int) $row['impressions'],
                'ctr' => $row['ctr'],
                'position' => $row['position'],
            ];
        }

        DailyMetric::upsert($rows, ['project_id', 'date'], ['clicks', 'impressions', 'ctr', 'position']);
    }

    private function syncKeyword(SearchConsole $gsc, Project $project, Keyword $keyword, string $start, string $end): void
    {
        $rows = [];

        foreach ($gsc->query($project->gsc_property, $start, $end, ['date'], ['query' => $keyword->query]) as $row) {
            $rows[$row['keys'][0]] = [
                'keyword_id' => $keyword->id,
                'date' => $row['keys'][0],
                'position' => $row['position'],
                'clicks' => (int) $row['clicks'],
                'impressions' => (int) $row['impressions'],
                'top_page' => null,
            ];
        }

        if (! $rows) {
            return;
        }

        // Page qui reçoit le plus de clics (puis d'impressions) sur la période récente.
        $recentStart = Carbon::parse($end)->subDays(27)->toDateString();
        $pages = collect($gsc->query($project->gsc_property, $recentStart, $end, ['page'], ['query' => $keyword->query], 50))
            ->sortByDesc(fn ($r) => [$r['clicks'], $r['impressions']]);

        $lastDate = max(array_keys($rows));
        $rows[$lastDate]['top_page'] = $pages->first()['keys'][0] ?? null;

        KeywordMetric::upsert(array_values($rows), ['keyword_id', 'date'], ['position', 'clicks', 'impressions', 'top_page']);
    }
}
