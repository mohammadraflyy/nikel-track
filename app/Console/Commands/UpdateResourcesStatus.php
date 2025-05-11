<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateResourcesStatus extends Command
{
    protected $signature = 'resources:update-status';
    protected $description = 'Update vehicle and driver statuses based on active bookings';

    public function handle()
    {
        $now = Carbon::now()->timezone(config('app.timezone'));

        DB::transaction(function () use ($now) {
            DB::table('vehicles')->update(['status' => 'available']);
            DB::table('drivers')->update(['status' => 'available']);

            Booking::activeAt($now)
                ->with(['vehicle', 'driver'])
                ->chunk(100, function ($bookings) {
                    foreach ($bookings as $booking) {
                        $booking->vehicle()->update(['status' => 'on_duty']);
                        $booking->driver()->update(['status' => 'on_duty']);
                    }
                });
        });

        $this->info('Successfully updated statuses at ' . $now->toDateTimeString());
    }
}