<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Models\Promote;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');



use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $expired = Promote::where('is_active', true)
        ->where('end_date', '<', now())
        ->update([
            'is_active'  => false,
            'expired_at' => now(),
        ]);

    Log::info("🧹 Expired {$expired} promotions at " . now());
})->everyMinute();