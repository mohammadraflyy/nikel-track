<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('resources:update-status')->everyFifteenSeconds();