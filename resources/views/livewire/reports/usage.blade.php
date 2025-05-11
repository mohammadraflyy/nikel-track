<?php

use Livewire\Volt\Component;
use App\Models\UsageLog;
use App\Models\SystemLog;
use App\Exports\UsageLogsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount()
    {
        // Set default date range to last 30 days
        $this->dateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');
    }

    public function export()
    {
        try {
            $validated = $this->validate([
                'dateFrom' => 'required|date',
                'dateTo' => 'required|date|after_or_equal:dateFrom',
            ]);

            // Log export attempt with date range
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'export_attempt',
                'table_name' => 'usage_logs',
                'record_id' => null,
                'description' => 'Attempting to export usage logs from ' . 
                               $this->dateFrom . ' to ' . $this->dateTo
            ]);

            $fileName = 'usage-logs-' . $this->dateFrom . '_to_' . $this->dateTo . '.xlsx';

            // Get statistics for logging
            $recordCount = UsageLog::whereBetween('created_at', [$this->dateFrom, $this->dateTo])->count();
            $totalDistance = UsageLog::whereBetween('created_at', [$this->dateFrom, $this->dateTo])
                              ->sum(\DB::raw('end_km - start_km'));
            $totalFuel = UsageLog::whereBetween('created_at', [$this->dateFrom, $this->dateTo])
                           ->sum('fuel_used');

            $export = Excel::download(
                new UsageLogsExport([
                    'date_from' => $this->dateFrom,
                    'date_to' => $this->dateTo,
                ]),
                $fileName
            );

            // Log successful export with statistics
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'export_success',
                'table_name' => 'usage_logs',
                'record_id' => null,
                'description' => 'Exported ' . $recordCount . ' usage logs | ' .
                               'Total distance: ' . $totalDistance . ' km | ' .
                               'Total fuel used: ' . $totalFuel . ' liters | ' .
                               'File: ' . $fileName
            ]);

            return $export;

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'export_failed',
                'table_name' => 'usage_logs',
                'record_id' => null,
                'description' => 'Failed to export usage logs: ' . $e->getMessage() . 
                               ' | Parameters: ' . json_encode([
                                    'dateFrom' => $this->dateFrom,
                                    'dateTo' => $this->dateTo
                                ])
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to export usage logs: ' . $e->getMessage(),
                'type' => 'error'
            ]);

            throw $e;
        }
    }

    public function clearDates()
    {
        $this->reset(['dateFrom', 'dateTo']);
        
        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'filter_reset',
            'table_name' => 'usage_logs',
            'record_id' => null,
            'description' => 'Reset usage log export date filters'
        ]);
    }

    public function setLast30Days()
    {
        $this->dateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->dateTo = Carbon::now()->format('Y-m-d');
        
        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'date_range_set',
            'table_name' => 'usage_logs',
            'record_id' => null,
            'description' => 'Set date range to last 30 days'
        ]);
    }

    public function setCurrentMonth()
    {
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
        
        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'date_range_set',
            'table_name' => 'usage_logs',
            'record_id' => null,
            'description' => 'Set date range to current month'
        ]);
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Usage Logs Export', 'url' => '#'],
        ]" />
                
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Usage Logs Export</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Export vehicle usage records by date range</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">From Date *</label>
                    <input 
                        wire:model="dateFrom" 
                        type="date" 
                        required
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                </div>
                
                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">To Date *</label>
                    <input 
                        wire:model="dateTo" 
                        type="date" 
                        required
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                </div>
            </div>

            <div class="flex justify-end">
                <button 
                    wire:click="export"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span wire:loading.remove>Export Usage Logs</span>
                    <span wire:loading>Preparing Export...</span>
                </button>
            </div>
        </div>
    </div>
</div>