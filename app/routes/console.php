<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule reminder notifications every minute
Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule poll auto-close check every minute
Schedule::command('polls:close-expired')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Schedule arxiv AI papers fetch daily at 6AM
Schedule::command('arxiv:fetch')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/arxiv-fetch.log'));
