<div class="space-y-6">
    <x-page-header title="Suivi de positions" subtitle="Position moyenne Google par mot-clé, d'après la Search Console (données à J-2)." />

    @unless ($project->gsc_property)
        <div class="card text-sm text-amber-700">Ce projet n'a pas de propriété Search Console : les mots-clés n'auront pas de données. <a class="underline" href="{{ route('projects.settings', $project) }}" wire:navigate>Réglages</a></div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-2">
        <form wire:submit="add" class="card space-y-3">
            <label class="label">Ajouter des mots-clés <span class="font-normal text-slate-400">(un par ligne)</span></label>
            <textarea wire:model="newKeywords" rows="4" class="input" placeholder="agence seo lyon&#10;audit seo gratuit"></textarea>
            @error('newKeywords')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="btn" wire:loading.attr="disabled" wire:target="add">Suivre</button>
        </form>

        <div class="card space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Suggestions</h2>
                <button wire:click="loadSuggestions" class="btn-ghost" wire:loading.attr="disabled" wire:target="loadSuggestions">
                    <span wire:loading.remove wire:target="loadSuggestions">Requêtes non suivies</span>
                    <span wire:loading wire:target="loadSuggestions">Chargement…</span>
                </button>
            </div>
            @if ($suggestionsError)
                <p class="text-sm text-red-600">{{ $suggestionsError }}</p>
            @elseif ($suggestions)
                <div class="max-h-56 overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr><th class="th">Requête</th><th class="th text-right">Impr.</th><th class="th text-right">Pos.</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($suggestions as $s)
                                <tr wire:key="sug-{{ md5($s['query']) }}">
                                    <td class="td">{{ $s['query'] }}</td>
                                    <td class="td text-right tabular-nums">{{ number_format($s['impressions'], 0, ',', ' ') }}</td>
                                    <td class="td text-right tabular-nums">{{ number_format($s['position'], 1, ',', ' ') }}</td>
                                    <td class="td text-right"><button wire:click="track(@js($s['query']))" class="text-indigo-600 hover:underline">Suivre</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500">Affiche les requêtes qui t'apportent déjà des impressions sur les 28 derniers jours, sans être suivies.</p>
            @endif
        </div>
    </div>

    @if ($history)
        <div class="card">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold">Historique : {{ $history['query'] }}</h2>
                <button wire:click="$set('selected', null)" class="btn-ghost">Fermer</button>
            </div>
            <x-chart height="h-64" :config="[
                'labels' => $history['labels'],
                'datasets' => [
                    ['label' => 'Position', 'data' => $history['position'], 'borderColor' => '#6366f1', 'yAxisID' => 'y'],
                    ['label' => 'Clics', 'data' => $history['clicks'], 'type' => 'bar', 'backgroundColor' => 'rgba(16,185,129,.35)', 'yAxisID' => 'y1'],
                ],
                'scales' => [
                    'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 8, 'maxRotation' => 0]],
                    'y' => ['reverse' => true, 'min' => 1, 'title' => ['display' => true, 'text' => 'Position']],
                    'y1' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'Clics']],
                ],
            ]" />
        </div>
    @endif

    <div class="card overflow-x-auto p-0">
        @php
            $sortLink = fn ($key, $label, $align = '') => '<button wire:click="$set(\'sort\', \''.$key.'\')" class="th '.$align.' '.($sort === $key ? 'text-indigo-600' : '').'">'.$label.'</button>';
        @endphp
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead>
                <tr>
                    <th>{!! $sortLink('query', 'Mot-clé') !!}</th>
                    <th class="text-right">{!! $sortLink('position', 'Position') !!}</th>
                    <th class="text-right">{!! $sortLink('change', 'Évol. 7 j') !!}</th>
                    <th class="th">30 j</th>
                    <th class="text-right">{!! $sortLink('clicks', 'Clics 28 j') !!}</th>
                    <th class="text-right">{!! $sortLink('impressions', 'Impr. 28 j') !!}</th>
                    <th class="th">Page</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($rows as $r)
                    <tr wire:key="kw-{{ $r['id'] }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="td"><button wire:click="$set('selected', {{ $r['id'] }})" class="text-left font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $r['query'] }}</button></td>
                        <td class="td text-right tabular-nums">{{ $r['position'] !== null ? number_format($r['position'], 1, ',', ' ') : '—' }}</td>
                        <td class="td text-right tabular-nums">
                            @if ($r['change'] !== null && abs($r['change']) >= 0.1)
                                <span class="{{ $r['change'] > 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $r['change'] > 0 ? '▲' : '▼' }} {{ number_format(abs($r['change']), 1, ',', ' ') }}</span>
                            @else
                                <span class="text-slate-400">=</span>
                            @endif
                        </td>
                        <td class="td"><x-sparkline :values="$r['spark']" reverse /></td>
                        <td class="td text-right tabular-nums">{{ number_format($r['clicks'], 0, ',', ' ') }}</td>
                        <td class="td text-right tabular-nums">{{ number_format($r['impressions'], 0, ',', ' ') }}</td>
                        <td class="td max-w-xs truncate text-xs text-slate-500">
                            @if ($r['page'])<a href="{{ $r['page'] }}" target="_blank" rel="noopener" class="hover:underline">{{ preg_replace('#^https?://[^/]+#', '', $r['page']) ?: '/' }}</a>@endif
                        </td>
                        <td class="td text-right"><button wire:click="remove({{ $r['id'] }})" wire:confirm="Ne plus suivre « {{ $r['query'] }} » ? Son historique sera supprimé." class="text-slate-400 hover:text-red-600" aria-label="Supprimer">✕</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="td py-8 text-center text-slate-500">Aucun mot-clé suivi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
