<div class="space-y-6">
    <x-page-header :title="$project->name" :subtitle="$project->gsc_property
        ? 'Search Console : '.$project->gsc_property.' · synchronisé '.($project->gsc_synced_at?->diffForHumans() ?? 'jamais')
        : 'Aucune propriété Search Console associée'">
        @if ($project->gsc_property)
            <button wire:click="sync" class="btn-ghost" wire:loading.attr="disabled">Synchroniser</button>
        @endif
        <a href="{{ $project->url }}" target="_blank" rel="noopener" class="btn-ghost">Ouvrir le site ↗</a>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Clics (28 j.)" :value="$summary['clicks']" :previous="$summary['prev']['clicks'] ?? null" />
        <x-kpi label="Impressions (28 j.)" :value="$summary['impressions']" :previous="$summary['prev']['impressions'] ?? null" />
        <x-kpi label="CTR moyen" :value="$summary['ctr']" :previous="$summary['prev']['ctr'] ?? null" format="pct" />
        <x-kpi label="Position moyenne" :value="$summary['position']" :previous="$summary['prev']['position'] ?? null" format="dec" lower-is-better />
    </div>

    <div class="card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Trafic organique</h2>
            <div class="flex gap-1 text-sm">
                @foreach ([28 => '28 j', 90 => '3 mois', 180 => '6 mois', 365 => '1 an', 480 => '16 mois'] as $d => $label)
                    <button wire:click="$set('days', {{ $d }})"
                            class="rounded-md px-2 py-1 {{ $days === $d ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        @if (count($chart['labels']))
            <x-chart height="h-72" :config="[
                'labels' => $chart['labels'],
                'datasets' => [
                    ['label' => 'Clics', 'data' => $chart['clicks'], 'borderColor' => '#6366f1', 'backgroundColor' => 'rgba(99,102,241,.1)', 'fill' => true, 'yAxisID' => 'y'],
                    ['label' => 'Impressions', 'data' => $chart['impressions'], 'borderColor' => '#a855f7', 'yAxisID' => 'y1'],
                ],
                'scales' => [
                    'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 8, 'maxRotation' => 0]],
                    'y' => ['position' => 'left', 'beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Clics']],
                    'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'Impressions']],
                ],
            ]" />
        @else
            <p class="py-12 text-center text-sm text-slate-500">
                @if ($project->gsc_property)
                    Pas encore de données. Lance la file d'attente (<code>php artisan queue:work</code>) puis clique sur « Synchroniser ».
                @else
                    Associe une propriété Search Console dans les <a href="{{ route('projects.settings', $project) }}" wire:navigate class="text-indigo-600 underline">réglages</a> pour voir le trafic.
                @endif
            </p>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Mots-clés --}}
        <div class="card lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">Meilleures positions</h2>
                <a href="{{ route('projects.rankings', $project) }}" wire:navigate class="text-sm text-indigo-600 hover:underline">Tous les mots-clés →</a>
            </div>
            @if ($keywords->isEmpty())
                <p class="text-sm text-slate-500">Aucun mot-clé suivi avec des données.</p>
            @else
                <table class="min-w-full text-sm">
                    <thead><tr><th class="th">Mot-clé</th><th class="th text-right">Position</th><th class="th text-right">Clics / jour</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($keywords as $k)
                            <tr>
                                <td class="td">{{ $k['query'] }}</td>
                                <td class="td text-right tabular-nums">{{ number_format($k['m']->position, 1, ',', ' ') }}</td>
                                <td class="td text-right tabular-nums">{{ $k['m']->clicks }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Répartition des positions --}}
        <div class="card">
            <h2 class="mb-3 font-semibold">Répartition des positions</h2>
            @if (array_sum($positions))
                <x-chart height="h-56" :config="[
                    'type' => 'bar',
                    'labels' => array_keys($positions),
                    'datasets' => [['label' => 'Mots-clés', 'data' => array_values($positions), 'backgroundColor' => ['#10b981', '#6366f1', '#a855f7', '#f59e0b', '#94a3b8'], 'borderRadius' => 4]],
                ]" />
            @else
                <p class="text-sm text-slate-500">Ajoute des mots-clés pour voir leur répartition.</p>
            @endif
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <a href="{{ route('projects.backlinks', $project) }}" wire:navigate class="card block hover:border-indigo-300">
            <p class="kpi-label">Domaines référents</p>
            @if ($domains)
                <p class="kpi-value">{{ number_format($domains->rows_count, 0, ',', ' ') }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    Import du {{ $domains->created_at->format('d/m/Y') }}
                    @if ($domainsDiff['previous'])
                        · <span class="text-emerald-600">+{{ count($domainsDiff['new']) }}</span>
                        / <span class="text-red-600">−{{ count($domainsDiff['lost']) }}</span>
                    @endif
                </p>
            @else
                <p class="mt-1 text-sm text-slate-500">Importe le rapport « Liens » de la Search Console.</p>
            @endif
        </a>
        <a href="{{ route('projects.audit', $project) }}" wire:navigate class="card block hover:border-indigo-300">
            <p class="kpi-label">Santé technique</p>
            @if ($crawl)
                <p class="kpi-value">{{ $crawl->health_score }} %</p>
                <p class="mt-1 text-xs text-slate-500">{{ $crawl->pages_count }} pages · {{ $crawl->issues_count }} problèmes · {{ $crawl->finished_at->format('d/m/Y') }}</p>
            @else
                <p class="mt-1 text-sm text-slate-500">Aucun audit terminé. Lance le premier.</p>
            @endif
        </a>
    </div>
</div>
