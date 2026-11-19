<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('lessons:generate')->dailyAt('01:00');
Schedule::command('debts:remind')->monthlyOn(1, '10:00');
Schedule::command('debts:remind')->weeklyOn(1, '10:00')->when(fn () => now()->day !== 1);
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('isup:sync-events')->everyTwoMinutes()->withoutOverlapping()->runInBackground();
Schedule::command('isup:reconcile')->hourly()->withoutOverlapping()->runInBackground();
