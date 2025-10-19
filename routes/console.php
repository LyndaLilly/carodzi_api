<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::call(function () {
    \App\Models\Promote::where('is_active', true)
        ->where('end_date', '<', now())
        ->update([
            'is_active' => false,
            'expired_at' => now(),
        ]);
})->daily();