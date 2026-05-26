<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about', function () {
    $this->comment('FluxConvert Laravel port');
})->purpose('Show information about this application');

// Remove leftover uploaded/converted files so storage does not fill up over time.
Schedule::command('files:cleanup')->hourly();
