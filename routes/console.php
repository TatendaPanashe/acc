<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscription lifecycle (requires the Laravel scheduler: `php artisan schedule:work` or a cron entry).
\Illuminate\Support\Facades\Schedule::command('billing:run')->hourly()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('annuity:bill')->dailyAt('06:00')->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('ai:refresh')->dailyAt('02:30')->withoutOverlapping();
