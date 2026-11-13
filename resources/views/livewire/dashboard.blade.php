<div class="space-y-6">
    <x-page-header title="Tableau de bord" subtitle="Tous tes sites suivis, sur les 28 derniers jours de données Search Console.">
        <button wire:click="openForm" class="btn">+ Ajouter un site</button>
    </x-page-header>

    {{-- Connexion Google --}}
    <div class="card flex flex-wrap items-center justify-between gap-3">
        @if ($google)
            <p class="text-sm">Search Console connectée avec <strong>{{ $google->email }}</strong>.</p>
            <form method="POST" action="{{ route('google.disconnect') }}">
                @csrf
                <button class="btn-ghost">Déconnecter Google</button>
            </form>
        @else
            <p class="text-sm">Connecte ton compte Google pour importer les clics, impressions et positions de tes sites.</p>
            <a href="{{ route('google.connect') }}" class="btn">Connecter la Search Console</a>
        @endif
    </div>

    {{-- Formulaire d'ajout --}}
    @if ($showForm)
        <form wire:submit="create" class="card grid gap-4 md:grid-cols-3">
            <div class="md:col-span-3">
                <label class="label">Propriété Search Console <span class="font-normal text-slate-400">(facultatif)</span></label>
                @if ($propertiesError)
                    <p class="text-sm text-red-600">{{ $propertiesError }}</p>
                @elseif (! $google)
                    <p class="text-sm text-slate-500">Sans compte Google connecté, le site aura uniquement l'audit technique et l'import de backlinks.</p>
                @else
                    <select wire:model.live="gscProperty" class="input">
                        <option value="">— Aucune —</option>
                        @foreach ($properties as $property)
                            <option value="{{ $property }}">{{ $property }}</option>
                        @endforeach
                    </select>
                @endif
                @error('gscProperty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Nom</label>
                <input wire:model="name" class="input" placeholder="Mon site">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="label">URL de départ (pour l'audit)</label>
                <input wire:model="url" class="input" placeholder="https://exemple.fr/">
                @error('url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex gap-2 md:col-span-3">
                <button class="btn">Créer le projet</button>
                <button type="button" wire:click="$set('showForm', false)" class="btn-ghost">Annuler</button>
            </div>
        </form>
    @endif

    {{-- Liste des projets --}}
    @if ($this->projects->isEmpty())
        <div class="card text-center text-sm text-slate-500">Aucun site pour l'instant. Clique sur « Ajouter un site » pour commencer.</div>
    @else
        <div class="card overflow-x-auto p-0">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead>
                    <tr>
                        <th class="th">Site</th>
                        <th class="th text-right">Clics</th>
                        <th class="th text-right">Impressions</th>
                        <th class="th text-right">Position moy.</th>
                        <th class="th">Clics (3 mois)</th>
                        <th class="th text-right">Mots-clés</th>
                        <th class="th text-right">Domaines réf.</th>
                        <th class="th text-right">Santé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($this->projects as $project)
                        @php
                            $s = $project->stats;
                            $delta = ($s['prev']['clicks'] ?? 0) ? ($s['clicks'] - $s['prev']['clicks']) / $s['prev']['clicks'] : null;
                            $health = $project->latestCrawl?->health_score;
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="td">
                                <a href="{{ route('projects.overview', $project) }}" wire:navigate class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $project->name }}</a>
                                <p class="text-xs text-slate-400">{{ $project->host() }}@unless ($project->gsc_property) · sans Search Console @endunless</p>
                            </td>
                            <td class="td text-right tabular-nums">
                                {{ number_format($s['clicks'], 0, ',', ' ') }}
                                @if ($delta !== null)
                                    <span class="block text-xs {{ $delta >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $delta >= 0 ? '+' : '' }}{{ number_format($delta * 100, 0) }} %</span>
                                @endif
                            </td>
                            <td class="td text-right tabular-nums">{{ number_format($s['impressions'], 0, ',', ' ') }}</td>
                            <td class="td text-right tabular-nums">{{ $s['position'] ? number_format($s['position'], 1, ',', ' ') : '—' }}</td>
                            <td class="td"><x-sparkline :values="$project->sparkline" /></td>
                            <td class="td text-right tabular-nums">{{ $project->keywords_count }}</td>
                            <td class="td text-right tabular-nums">{{ $project->domainsCount !== null ? number_format($project->domainsCount, 0, ',', ' ') : '—' }}</td>
                            <td class="td text-right">
                                @if ($health !== null)
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $health >= 80 ? 'bg-emerald-100 text-emerald-700' : ($health >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $health }} %</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
