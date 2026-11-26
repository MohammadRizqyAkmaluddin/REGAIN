<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('regain:process-recurring-incomes')
    ->hourly()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

Schedule::command('regain:schedule-motivational-pushes')
    ->everyFifteenMinutes()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

Schedule::command('regain:snapshot-smoking-savings')
    ->dailyAt('00:05')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
