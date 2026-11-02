<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
// Every morning: detect low stock and prepare draft purchase orders.
Schedule::command('reorder:check')
    ->dailyAt('06:00')
    ->withoutOverlapping();
    
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
