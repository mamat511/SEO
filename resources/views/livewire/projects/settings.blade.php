<div class="space-y-6">
    <x-page-header title="Réglages du projet" />

    <form wire:submit="save" class="card max-w-2xl space-y-4">
        <div>
            <label class="label">Nom</label>
            <input wire:model="name" class="input">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">URL de départ de l'audit</label>
            <input wire:model="url" class="input">
            @error('url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">Propriété Search Console</label>
            @if ($propertiesError)
                <p class="text-sm text-red-600">{{ $propertiesError }}
                    @unless (auth()->user()->googleAccount)<a href="{{ route('google.connect') }}" class="underline">Connecter Google</a>@endunless
                </p>
            @endif
            @if ($properties || $gscProperty)
                <select wire:model="gscProperty" class="input">
                    <option value="">— Aucune —</option>
                    @foreach (array_unique(array_filter(array_merge($properties, [$project->gsc_property]))) as $property)
                        <option value="{{ $property }}">{{ $property }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Changer de propriété efface l'historique importé et relance une synchronisation complète.</p>
            @endif
            @error('gscProperty')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <button class="btn">Enregistrer</button>
    </form>

    <div class="card max-w-2xl border-red-200 dark:border-red-900">
        <h2 class="font-semibold text-red-700 dark:text-red-400">Supprimer le projet</h2>
        <p class="mt-1 text-sm text-slate-500">Supprime définitivement les mots-clés, l'historique, les imports de backlinks et les audits de ce site.</p>
        <button wire:click="delete" wire:confirm="Supprimer définitivement « {{ $project->name }} » et toutes ses données ?" class="mt-3 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500">Supprimer</button>
    </div>
</div>
