<?php

use Illuminate\Support\Facades\Schedule;

// Runs from the cPanel cron (`* * * * * php artisan schedule:run`).
Schedule::command('asuh:expire-reservations')->everyMinute()->withoutOverlapping();

// Database queue (receipt e-mails etc.); cPanel has no long-running workers.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

// Weekly reading report to parents, Monday morning (Asia/Kuala_Lumpur).
Schedule::command('asuh:weekly-reports')->weeklyOn(1, '8:00')->withoutOverlapping();
