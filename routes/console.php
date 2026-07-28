<?php

use Illuminate\Support\Facades\Schedule;

// UC-29: Check for due/overdue calibration daily at 7am
Schedule::command('notifications:calibration-due')->dailyAt('07:00');
