<div class="space-y-6">
    <x-page-header title="Backlinks" subtitle="D'après les exports CSV du rapport « Liens » de la Search Console. Chaque import est comparé au précédent." />

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="import" class="card space-y-3 lg:col-span-1">
            <h2 class="font-semibold">Importer un export</h2>
            <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-500">
                <li>Search Console → rapport <strong>Liens</strong>.</li>
                <li>Bloc « Sites les plus référents » (domaines) ou « Exemples de liens » (pages) → <strong>Exporter</strong> en CSV.</li>
                <li>Dépose le fichier ici. Le type est détecté automatiquement.</li>
            </ol>
            <p class="text-xs text-slate-400">Les intitulés exacts peuvent varier selon la version de la Search Console.</p>
            <input type="file" wire:model="file" accept=".csv,text/csv" class="block w-full text-sm">
            <div wire:loading wire:target="file" class="text-sm text-slate-500">Envoi…</div>
            @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="btn" wire:loading.attr="disabled" wire:target="file,import">Importer</button>
        </form>

        <div class="grid gap-4 sm:grid-cols-3 lg:col-span-2">
            <div class="card">
                <p class="kpi-label">{{ $kind === 'domains' ? 'Domaines référents' : 'Liens' }}</p>
                <p class="kpi-value">{{ $latest ? number_format($latest->rows_count, 0, ',', ' ') : '—' }}</p>
                @if ($latest)<p class="mt-1 text-xs text-slate-500">Import du {{ $latest->created_at->format('d/m/Y') }}</p>@endif
            </div>
            <div class="card">
                <p class="kpi-label">Nouveaux</p>
                <p class="kpi-value text-emerald-600">{{ $diff['previous'] ? '+'.count($diff['new']) : '—' }}</p>
                @if ($diff['previous'])<p class="mt-1 text-xs text-slate-500">depuis le {{ $diff['previous']->created_at->format('d/m/Y') }}</p>@endif
            </div>
            <div class="card">
                <p class="kpi-label">Perdus</p>
                <p class="kpi-value text-red-600">{{ $diff['previous'] ? '−'.count($diff['lost']) : '—' }}</p>
                @if (! $diff['previous'] && $latest)<p class="mt-1 text-xs text-slate-500">Un 2ᵉ import permettra la comparaison.</p>@endif
            </div>
            @if (count($chart['labels']) > 1)
                <div class="card sm:col-span-3">
                    <h2 class="mb-2 text-sm font-semibold">Évolution des domaines référents</h2>
                    <x-chart height="h-40" :config="[
                        'labels' => $chart['labels'],
                        'datasets' => [['label' => 'Domaines', 'data' => $chart['domains'], 'borderColor' => '#6366f1']],
                        'options' => ['elements' => ['point' => ['radius' => 3]]],
                    ]" />
                </div>
            @endif
        </div>
    </div>

    <div class="card space-y-4 p-0">
        <div class="flex flex-wrap items-center gap-2 px-4 pt-4">
            @foreach (['domains' => 'Domaines référents', 'links' => 'Liens'] as $k => $label)
                <button wire:click="$set('kind', '{{ $k }}')" class="rounded-md px-3 py-1.5 text-sm {{ $kind === $k ? 'bg-indigo-600 text-white' : 'btn-ghost' }}">{{ $label }}</button>
            @endforeach
            <span class="mx-2 h-5 w-px bg-slate-200 dark:bg-slate-700"></span>
            @foreach (['all' => 'Tous', 'new' => 'Nouveaux', 'lost' => 'Perdus'] as $f => $label)
                <button wire:click="$set('filter', '{{ $f }}')" class="rounded-md px-3 py-1.5 text-sm {{ $filter === $f ? 'bg-slate-800 text-white dark:bg-slate-200 dark:text-slate-900' : 'btn-ghost' }}">{{ $label }}</button>
            @endforeach
            <input wire:model.live.debounce.300ms="search" class="input ml-auto max-w-xs" placeholder="Filtrer…">
        </div>

        @if ($rows)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead>
                        <tr>
                            <th class="th">{{ $kind === 'domains' ? 'Domaine' : 'Page source' }}</th>
                            @if ($kind === 'domains')
                                <th class="th text-right">Pages avec lien</th>
                                <th class="th text-right">Pages cibles</th>
                            @else
                                <th class="th">Domaine</th>
                                <th class="th text-right">Dernière exploration</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($rows as $b)
                            <tr wire:key="bl-{{ $b->id }}">
                                <td class="td max-w-lg truncate">
                                    @php $href = $kind === 'domains' ? 'https://'.$b->source : $b->source; @endphp
                                    <a href="{{ $href }}" target="_blank" rel="noopener nofollow" class="hover:underline">{{ $b->source }}</a>
                                    @if ($filter === 'all' && in_array($b->source, $diff['new']))<span class="ml-1 rounded bg-emerald-100 px-1.5 text-xs text-emerald-700">nouveau</span>@endif
                                </td>
                                @if ($kind === 'domains')
                                    <td class="td text-right tabular-nums">{{ $b->linking_pages !== null ? number_format($b->linking_pages, 0, ',', ' ') : '—' }}</td>
                                    <td class="td text-right tabular-nums">{{ $b->target_pages ?? '—' }}</td>
                                @else
                                    <td class="td text-slate-500">{{ $b->domain }}</td>
                                    <td class="td text-right tabular-nums">{{ $b->last_crawled?->format('d/m/Y') ?? '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="3" class="td py-8 text-center text-slate-500">Aucun résultat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 pb-4">{{ $rows->links() }}</div>
        @else
            <p class="px-4 pb-6 text-sm text-slate-500">Aucun import de ce type pour l'instant.</p>
        @endif
    </div>

    @if ($imports->isNotEmpty())
        <details class="card">
            <summary class="cursor-pointer text-sm font-semibold">Historique des imports ({{ $imports->count() }})</summary>
            <table class="mt-3 min-w-full text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($imports as $i)
                        <tr wire:key="imp-{{ $i->id }}">
                            <td class="td">{{ $i->created_at->format('d/m/Y H:i') }}</td>
                            <td class="td">{{ $i->kind === 'domains' ? 'Domaines' : 'Liens' }}</td>
                            <td class="td text-slate-500">{{ $i->filename }}</td>
                            <td class="td text-right tabular-nums">{{ number_format($i->rows_count, 0, ',', ' ') }}</td>
                            <td class="td text-right"><button wire:click="deleteImport({{ $i->id }})" wire:confirm="Supprimer cet import ?" class="text-slate-400 hover:text-red-600">Supprimer</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
