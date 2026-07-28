<?php

use App\Console\Commands\DetectSuspiciousSolvesCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Anti-abuse: flag accounts with suspicious hard-solve bursts (plan §11).
Schedule::command(DetectSuspiciousSolvesCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();
