<?php

namespace App\Exports;

use App\Models\ServiceLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class ServiceLogsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        return ServiceLog::query()
            ->with(['vehicle'])
            ->where('service_date', '>=', $this->filters['date_from'])
            ->where('service_date', '<=', $this->filters['date_to'])
            ->latest();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Vehicle',
            'Service Date',
            'Service Type',
            'Description',
            'Cost',
            'Created At',
            'Updated At'
        ];
    }

    public function map($serviceLog): array
    {
        return [
            $serviceLog->id,
            $serviceLog->vehicle->license_plate ?? 'N/A',
            Carbon::parse($serviceLog->service_date)->format('Y-m-d'),
            $serviceLog->service_type,
            $serviceLog->description ?? '',
            number_format($serviceLog->cost, 2),
            $serviceLog->created_at->format('Y-m-d H:i:s'),
            $serviceLog->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}