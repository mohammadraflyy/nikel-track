<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\ServiceLog;
use App\Models\Vehicle;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $serviceLogs = [];
    public string $search = '';
    public string $vehicleFilter = '';
    public string $dateFilter = '';
    public $logToDelete = null;
    public bool $showDeleteModal = false;

    public function serviceLogs()
    {
        try {
            $query = ServiceLog::query()
                ->with('vehicle')
                ->when($this->search, fn($q) => $q->where('description', 'like', "%{$this->search}%"))
                ->when($this->vehicleFilter, fn($q) => $q->where('vehicle_id', $this->vehicleFilter))
                ->when($this->dateFilter, fn($q) => $q->whereDate('service_date', $this->dateFilter))
                ->orderBy('service_date', 'desc');

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'page_load',
                'table_name' => 'service_logs',
                'record_id' => null,
                'description' => 'Viewed service logs listing page'
            ]);

            if ($this->search || $this->vehicleFilter || $this->dateFilter) {
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'search_filter',
                    'table_name' => 'service_logs',
                    'record_id' => null,
                    'description' => 'Filtered service logs with parameters: ' . json_encode([
                        'search' => $this->search,
                        'vehicle_id' => $this->vehicleFilter,
                        'date' => $this->dateFilter
                    ])
                ]);
            }

            return $query->paginate(10);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'service_logs',
                'record_id' => null,
                'description' => 'Failed to load service logs: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to load service logs: ' . $e->getMessage(),
                'type' => 'error'
            ]);

            return ServiceLog::query()->paginate(10);
        }
    }

    public function confirmDelete($id)
    {
        try {
            $this->logToDelete = $id;
            $this->showDeleteModal = true;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_initiated',
                'table_name' => 'service_logs',
                'record_id' => $id,
                'description' => 'Initiated deletion of service log ID: ' . $id
            ]);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'service_logs',
                'record_id' => $id,
                'description' => 'Failed to initiate service log deletion: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to initiate deletion: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    public function deleteLog()
    {
        try {
            $serviceLog = ServiceLog::findOrFail($this->logToDelete);

            // Log before deletion
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_attempt',
                'table_name' => 'service_logs',
                'record_id' => $serviceLog->id,
                'description' => 'Attempting to delete service log - ' .
                               'ID: ' . $serviceLog->id .
                               ' | Vehicle: ' . $serviceLog->vehicle_id .
                               ' | Service Type: ' . $serviceLog->service_type .
                               ' | Date: ' . $serviceLog->service_date->format('Y-m-d') .
                               ' | Cost: ' . number_format($serviceLog->cost, 2)
            ]);

            $serviceLog->delete();

            // Log successful deletion
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_success',
                'table_name' => 'service_logs',
                'record_id' => $serviceLog->id,
                'description' => 'Successfully deleted service log - ' .
                               'ID: ' . $serviceLog->id .
                               ' | Vehicle: ' . $serviceLog->vehicle_id
            ]);

            $this->showDeleteModal = false;
            $this->logToDelete = null;
            session()->flash('message', 'Service log deleted successfully!');

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_failed',
                'table_name' => 'service_logs',
                'record_id' => $this->logToDelete,
                'description' => 'Failed to delete service log - ' .
                               'ID: ' . $this->logToDelete .
                               ' | Error: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to delete service log: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Vehicle Monitoring', 'url' => '#'],
            ['text' => 'Service Logs', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Vehicle Service Logs</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track all vehicle service records</p>
            </div>
            <div>
                <a href="{{ route('service-logs.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Service Log
                </a>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="relative rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input 
                        wire:model.live.debounce.300ms="search" 
                        type="text" 
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 dark:bg-zinc-800 dark:text-white" 
                        placeholder="Search description...">
                </div>

                <select 
                    wire:model.live="vehicleFilter"
                    class="block w-full px-3 rounded-md border border-gray-300 dark:border-zinc-700 focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 dark:bg-zinc-800 dark:text-white">
                    <option value="">All Vehicles</option>
                    @foreach(Vehicle::orderBy('license_plate')->get() as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->license_plate }}</option>
                    @endforeach
                </select>

                <input 
                    wire:model.live="dateFilter"
                    type="date" 
                    class="block w-full px-3 rounded-md border border-gray-300 dark:border-zinc-700 focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 dark:bg-zinc-800 dark:text-white">
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vehicle</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Service Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($this->serviceLogs() as $log)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $log->service_date->format('d M Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $log->vehicle->license_plate }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $log->service_type }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    IDR {{ number_format($log->cost, 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ Str::limit($log->description, 50) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a 
                                            href="{{ route('service-logs.edit', $log->id) }}" 
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <button 
                                            wire:click="confirmDelete({{ $log->id }})" 
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center p-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No service logs found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new service log to get started</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->serviceLogs()->links() }}
            </div>
        </div>
    </div>

    <flux:modal wire:model="showDeleteModal">
        <flux:heading>
            Delete Service Log
        </flux:heading>

        <flux:text>
            Are you sure you want to delete this service log? This action cannot be undone.
        </flux:text>

        <div class="flex gap-2 mt-4">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" type="button" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="button" wire:click="deleteLog">Delete Log</flux:button>
        </div>
    </flux:modal>

    @if (session()->has('message'))
        <x-notification message="{{ session('message') }}" type="success" />
    @endif
</div>