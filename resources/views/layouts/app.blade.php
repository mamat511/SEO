@php
    $current = request()->route('project');
    $projects = auth()->user()->projects()->orderBy('name')->get(['id', 'name', 'url']);
    $nav = [
        'projects.overview' => 'Vue d’ensemble',
        'projects.rankings' => 'Positions',
        'projects.backlinks' => 'Backlinks',
        'projects.audit' => 'Audit technique',
        'projects.settings' => 'Réglages',
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Tableau de bord' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100" x-data="{ menu: false }">
<div class="flex min-h-screen">
    {{-- Barre latérale --}}
    <aside class="fixed inset-y-0 left-0 z-30 w-64 -translate-x-full border-r border-slate-200 bg-white transition md:static md:translate-x-0 dark:border-slate-800 dark:bg-slate-900"
           :class="menu && 'translate-x-0'">
        <div class="flex h-14 items-center gap-2 border-b border-slate-200 px-4 font-semibold dark:border-slate-800">
            <span class="grid size-7 place-items-center rounded-lg bg-indigo-600 text-xs text-white">SEO</span>
            <a href="{{ route('dashboard') }}" wire:navigate>{{ config('app.name') }}</a>
        </div>
        <nav class="space-y-6 p-3 text-sm">
            <a href="{{ route('dashboard') }}" wire:navigate
               class="block rounded-lg px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-indigo-50 font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Tous les projets
            </a>

            <div>
                <p class="px-3 pb-1 text-xs font-semibold tracking-wide text-slate-400 uppercase">Projets</p>
                @forelse ($projects as $p)
                    <a href="{{ route('projects.overview', $p) }}" wire:navigate
                       class="block truncate rounded-lg px-3 py-1.5 {{ $current?->id === $p->id ? 'font-medium text-indigo-700 dark:text-indigo-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ $p->name }}
                    </a>
                    @if ($current?->id === $p->id)
                        <div class="mb-2 ml-3 border-l border-slate-200 pl-2 dark:border-slate-700">
                            @foreach ($nav as $route => $label)
                                <a href="{{ route($route, $p) }}" wire:navigate
                                   class="block rounded-md px-3 py-1 {{ request()->routeIs($route) ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                @empty
                    <p class="px-3 text-slate-400">Aucun projet.</p>
                @endforelse
            </div>
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="absolute bottom-0 w-full border-t border-slate-200 p-3 text-sm dark:border-slate-800">
            @csrf
            <p class="truncate px-3 text-slate-500">{{ auth()->user()->email }}</p>
            <button class="mt-1 px-3 text-slate-500 hover:text-red-600">Se déconnecter</button>
        </form>
    </aside>
    <div class="fixed inset-0 z-20 bg-black/30 md:hidden" x-show="menu" x-cloak @click="menu = false"></div>

    <main class="min-w-0 flex-1">
        <header class="flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4 md:hidden dark:border-slate-800 dark:bg-slate-900">
            <button @click="menu = true" class="btn-ghost" aria-label="Menu">☰</button>
            <span class="font-semibold">{{ $title ?? config('app.name') }}</span>
        </header>
        <div class="mx-auto max-w-7xl space-y-6 p-4 md:p-8">
            @foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200', 'error' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'] as $type => $classes)
                @if (session($type))
                    <div class="rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($type) }}</div>
                @endif
            @endforeach
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
