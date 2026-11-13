@php use App\Support\AuditIssues; @endphp
<div class="space-y-6" @if ($running) wire:poll.3s @endif>
    <x-page-header title="Audit technique" :subtitle="'Exploration de '.$project->url.' (même domaine, robots.txt respecté).'">
        <form wire:submit="start" class="flex items-center gap-2">
            <label class="text-sm text-slate-500" for="maxPages">Max.</label>
            <input id="maxPages" type="number" wire:model="maxPages" min="1" max="10000" class="input w-24">
            <span class="text-sm text-slate-500">pages</span>
            <button class="btn" @disabled($running)>Lancer un audit</button>
        </form>
    </x-page-header>
    @error('maxPages')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

    @if (! $current)
        <div class="card text-center text-sm text-slate-500">Aucun audit pour l'instant.</div>
    @elseif ($running)
        <div class="card">
            <p class="font-medium">Audit en cours… {{ $current->pages_count }} / {{ $current->max_pages }} pages</p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div class="h-full bg-indigo-600 transition-all" style="width: {{ min(100, round(100 * $current->pages_count / max(1, $current->max_pages))) }}%"></div>
            </div>
            @if ($current->status === 'pending')
                <p class="mt-2 text-sm text-slate-500">En attente d'un worker. Vérifie que <code>php artisan queue:work</code> tourne.</p>
            @endif
        </div>
    @elseif ($current->status === 'failed')
        <div class="card text-sm text-red-600">L'audit a échoué : {{ $current->error }}</div>
    @else
        @php
            $bySeverity = collect($summary)->groupBy(fn ($n, $code) => AuditIssues::severity($code), true)->map->sum();
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="card">
                <p class="kpi-label">Santé</p>
                <p class="kpi-value {{ $current->health_score >= 80 ? 'text-emerald-600' : ($current->health_score >= 50 ? 'text-amber-600' : 'text-red-600') }}">{{ $current->health_score }} %</p>
                @if ($previous)
                    @php $d = $current->health_score - $previous->health_score; @endphp
                    <p class="mt-1 text-xs {{ $d >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $d >= 0 ? '+' : '' }}{{ $d }} pts vs audit précédent</p>
                @endif
            </div>
            <x-kpi label="Pages explorées" :value="$current->pages_count" :previous="$previous?->pages_count" />
            <div class="card"><p class="kpi-label">Erreurs</p><p class="kpi-value text-red-600">{{ $bySeverity[AuditIssues::ERROR] ?? 0 }}</p></div>
            <div class="card"><p class="kpi-label">Avertissements</p><p class="kpi-value text-amber-600">{{ $bySeverity[AuditIssues::WARNING] ?? 0 }}</p></div>
            <div class="card"><p class="kpi-label">Remarques</p><p class="kpi-value text-sky-600">{{ $bySeverity[AuditIssues::NOTICE] ?? 0 }}</p></div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card p-0 lg:col-span-1">
                <h2 class="px-4 pt-4 font-semibold">Problèmes</h2>
                <ul class="mt-2 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <li>
                        <button wire:click="$set('issue', '')" class="flex w-full justify-between px-4 py-2 text-left {{ $issue === '' ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                            <span>Toutes les pages</span><span class="tabular-nums text-slate-500">{{ $current->pages_count }}</span>
                        </button>
                    </li>
                    @forelse ($summary as $code => $count)
                        @php $sev = AuditIssues::severity($code); @endphp
                        <li>
                            <button wire:click="$set('issue', '{{ $code }}')" title="{{ AuditIssues::ALL[$code]['help'] ?? '' }}"
                                    class="flex w-full items-center justify-between gap-2 px-4 py-2 text-left {{ $issue === $code ? 'bg-indigo-50 dark:bg-indigo-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                <span class="flex items-center gap-2">
                                    <span class="size-2 shrink-0 rounded-full {{ $sev === 'error' ? 'bg-red-500' : ($sev === 'warning' ? 'bg-amber-500' : 'bg-sky-500') }}"></span>
                                    {{ AuditIssues::label($code) }}
                                </span>
                                <span class="tabular-nums text-slate-500">{{ $count }}</span>
                            </button>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-emerald-600">Aucun problème détecté 🎉</li>
                    @endforelse
                </ul>
            </div>

            <div class="card overflow-x-auto p-0 lg:col-span-2">
                @if ($issue && isset(AuditIssues::ALL[$issue]))
                    <p class="border-b border-slate-200 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">{{ AuditIssues::ALL[$issue]['help'] }}</p>
                @endif
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead><tr><th class="th">URL</th><th class="th text-right">Statut</th><th class="th">Title</th><th class="th text-right">Mots</th><th class="th text-right">ms</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($pages as $page)
                            <tr wire:key="page-{{ $page->id }}" class="align-top">
                                <td class="td max-w-xs">
                                    <a href="{{ $page->url }}" target="_blank" rel="noopener" class="break-all text-indigo-600 hover:underline dark:text-indigo-400">{{ preg_replace('#^https?://[^/]+#', '', $page->url) ?: '/' }}</a>
                                    @if ($page->redirect_to)<p class="break-all text-xs text-slate-500">→ {{ $page->redirect_to }}</p>@endif
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($page->issues ?? [] as $code)
                                            @php $sev = AuditIssues::severity($code); @endphp
                                            <span class="rounded px-1.5 text-xs {{ $sev === 'error' ? 'bg-red-100 text-red-700' : ($sev === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700') }}">{{ AuditIssues::label($code) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="td text-right tabular-nums {{ $page->status_code >= 400 || ! $page->status_code ? 'text-red-600' : ($page->status_code >= 300 ? 'text-amber-600' : '') }}">{{ $page->status_code ?? 'n/a' }}</td>
                                <td class="td max-w-xs text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($page->title, 80) }}</td>
                                <td class="td text-right tabular-nums">{{ $page->word_count ?? '—' }}</td>
                                <td class="td text-right tabular-nums {{ $page->response_ms > 1500 ? 'text-amber-600' : '' }}">{{ $page->response_ms ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="p-4">{{ $pages->links() }}</div>
            </div>
        </div>
    @endif

    @if ($crawls->count() > 1)
        <details class="card">
            <summary class="cursor-pointer text-sm font-semibold">Audits précédents</summary>
            <table class="mt-3 min-w-full text-sm">
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($crawls as $c)
                        <tr wire:key="crawl-{{ $c->id }}">
                            <td class="td"><button wire:click="$set('crawl', {{ $c->id }})" class="text-indigo-600 hover:underline">{{ $c->created_at->format('d/m/Y H:i') }}</button></td>
                            <td class="td">{{ ['pending' => 'En attente', 'running' => 'En cours', 'done' => 'Terminé', 'failed' => 'Échec'][$c->status] ?? $c->status }}</td>
                            <td class="td text-right tabular-nums">{{ $c->pages_count }} pages</td>
                            <td class="td text-right tabular-nums">{{ $c->health_score !== null ? $c->health_score.' %' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
