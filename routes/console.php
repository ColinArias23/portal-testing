<?php

use App\Console\Commands\ProcessAutoRenewals;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command(ProcessAutoRenewals::class)
    ->cron('0 0 1 6,12 *')
    ->runInBackground()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/auto-renewal.log'))
    ->onFailure(function (): void {
        Log::critical(
            'ProcessAutoRenewals scheduled job FAILED — check storage/logs/auto-renewal.log'
        );
    });