<?php

namespace App\Exports;

use App\Models\Booking;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class BookingsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return Booking::query()
            ->with(['vehicle', 'driver'])
            ->where('start_date', '>=', $this->filters['date_from'])
            ->where('end_date', '<=', $this->filters['date_to'])
            ->latest();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Vehicle',
            'Driver',
            'Start Date',
            'End Date',
            'Purpose',
            'Status',
            'Created At'
        ];
    }

    public function map($booking): array
    {
        return [
            $booking->id,
            $booking->vehicle->license_plate,
            $booking->driver->name,
            Carbon::parse($booking->start_date)->format('Y-m-d'),
            Carbon::parse($booking->end_date)->format('Y-m-d'),
            $booking->purpose,
            ucfirst($booking->status),
            $booking->created_at->format('Y-m-d H:i:s'),
        ];
    }
}