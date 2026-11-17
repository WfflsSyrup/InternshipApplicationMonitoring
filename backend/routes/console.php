<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Week 13 — Module 13 / SA7 automation sweeps (Asia/Manila)
|--------------------------------------------------------------------------
|
| All sweeps are idempotent: re-running a sweep on the same day (or the same
| ISO week) skips duplicate jobs instead of double-sending.
|
*/

Schedule::command('internship:sweep-incomplete')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

Schedule::command('internship:sweep-stalled')
    ->dailyAt('08:30')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

Schedule::command('internship:weekly-summary')
    ->weeklyOn(5, '17:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Week 14 — Module 14 / SA8 integration hygiene (Asia/Manila)
|--------------------------------------------------------------------------
|
| Consumed nonces are only replayable inside the clock-skew window, so rows
| older than their expiry can be pruned safely. Key rotation stays manual via
| `php artisan crypto:rotate-envelopes` so a coordinator decides when to roll.
|
*/

Schedule::command('integration:prune-nonces')
    ->dailyAt('02:15')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();