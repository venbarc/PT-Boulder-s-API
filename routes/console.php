<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pte:sync-nightly')
    ->dailyAt('22:00')
    ->timezone('America/Los_Angeles')
    ->withoutOverlapping();
