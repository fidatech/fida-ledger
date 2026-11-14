<?php

use Illuminate\Support\Facades\Schedule;

// Commands live in app/Console/Commands:
//   app:create-admin    a system administrator for /admin
//   ledger:recurring    posts the recurring Money In / Out that are due
//
// The server's cron runs "php artisan schedule:run" every minute. Recurring transactions are
// tried every hour because companies live in different time zones; a date is posted once
// however often this runs.
Schedule::command('ledger:recurring')->hourly()->withoutOverlapping();
