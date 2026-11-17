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