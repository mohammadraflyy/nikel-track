<?php

namespace App\Exports;

use App\Models\FuelLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class FuelLogsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return FuelLog::query()
            ->with(['vehicle'])
            ->where('log_date', '>=', $this->filters['date_from'])
            ->where('log_date', '<=', $this->filters['date_to'])
            ->latest();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Vehicle',
            'Amount',
            'Date',
            'Notes',
            'Created At',
            'Updated At'
        ];
    }

    public function map($fuelLog): array
    {
        return [
            $fuelLog->id,
            $fuelLog->vehicle->license_plate ?? 'N/A',
            number_format($fuelLog->amount, 2),
            Carbon::parse($fuelLog->log_date)->format('Y-m-d'),
            $fuelLog->notes ?? '',
            $fuelLog->created_at->format('Y-m-d H:i:s'),
            $fuelLog->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}