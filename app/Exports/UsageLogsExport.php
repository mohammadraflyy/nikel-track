<?php

namespace App\Exports;

use App\Models\UsageLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class UsageLogsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return UsageLog::query()
            ->with(['booking.vehicle', 'booking.driver'])
            ->whereHas('booking', function($query) {
                $query->where('start_date', '>=', $this->filters['date_from'])
                      ->where('end_date', '<=', $this->filters['date_to']);
            })
            ->latest();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Booking Reference',
            'Vehicle',
            'Driver',
            'Start KM',
            'End KM',
            'Distance (KM)',
            'Fuel Used (Liters)',
            'Notes',
            'Created At',
            'Updated At'
        ];
    }

    public function map($usageLog): array
    {
        $distance = $usageLog->end_km - $usageLog->start_km;

        return [
            $usageLog->id,
            $usageLog->booking->purpose ?? 'N/A',
            $usageLog->booking->vehicle->license_plate ?? 'N/A',
            $usageLog->booking->driver->name ?? 'N/A',
            number_format($usageLog->start_km),
            number_format($usageLog->end_km),
            number_format($distance),
            number_format($usageLog->fuel_used, 2),
            $usageLog->notes ?? '',
            $usageLog->created_at->format('Y-m-d H:i:s'),
            $usageLog->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}