<?php

use Illuminate\Support\Facades\Schedule;

$syncTime = config('copilot.sync_time', '06:00');

Schedule::command('copilot:sync-usage')->dailyAt($syncTime);
