<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Synchronisation Search Console quotidienne de tous les projets.
Schedule::call(function () {
    \App\Models\Project::whereNotNull('gsc_property')->each(fn ($p) => \App\Jobs\SyncSearchConsole::dispatch($p));
})->dailyAt('06:00')->name('sync-search-console')->withoutOverlapping();
