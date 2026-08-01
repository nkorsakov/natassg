<?php

use App\Console\Commands\DigestEveningCommand;
use App\Console\Commands\DigestMorningCommand;
use App\Console\Commands\DispatchRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$tz = config('notifications.timezone', config('app.timezone'));

Schedule::command(DispatchRemindersCommand::class)
    ->everyMinute()
    ->timezone($tz)
    ->withoutOverlapping();

Schedule::command(DigestMorningCommand::class)
    ->dailyAt(config('notifications.digest.morning', '10:00'))
    ->timezone($tz)
    ->withoutOverlapping();

Schedule::command(DigestEveningCommand::class)
    ->dailyAt(config('notifications.digest.evening', '22:00'))
    ->timezone($tz)
    ->withoutOverlapping();
