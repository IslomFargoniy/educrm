<?php

use App\Models\StudentVisit;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::command('lessons:generate')->dailyAt('01:00');
Schedule::command('debts:remind')->monthlyOn(1, '10:00');
Schedule::command('debts:remind')->weeklyOn(1, '10:00')->when(fn () => now()->day !== 1);
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('isup:sync-events')->everyTwoMinutes()->withoutOverlapping()->runInBackground();
Schedule::command('isup:reconcile')->hourly()->withoutOverlapping()->runInBackground();

// Terminal snapshots of passes are kept for 30 days.
Schedule::call(function (): void {
    $old = StudentVisit::query()->whereNotNull('photo')->where('scanned_at', '<', now()->subDays(30));
    Storage::disk('local')->delete($old->pluck('photo')->all());
    $old->update(['photo' => null]);
})->dailyAt('03:00')->name('visits:prune-photos');
