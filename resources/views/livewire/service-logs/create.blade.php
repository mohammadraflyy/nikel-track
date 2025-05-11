<?php

use Livewire\Volt\Component;
use App\Models\ServiceLog;
use App\Models\Vehicle;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public string $vehicle_id = '';
    public string $service_date = '';
    public string $service_type = '';
    public ?string $description = null;
    public string $cost = '';

    protected function rules()
    {
        return [
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'service_date' => ['required', 'date'],
            'service_type' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function save()
    {
        try {
            $validated = $this->validate();

            // Log creation attempt with input data
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_attempt',
                'table_name' => 'service_logs',
                'record_id' => null,
                'description' => 'Attempting to create service log with data: ' . json_encode([
                    'vehicle_id' => $validated['vehicle_id'],
                    'service_date' => $validated['service_date'],
                    'service_type' => $validated['service_type'],
                    'cost' => $validated['cost']
                ])
            ]);

            $serviceLog = ServiceLog::create([
                'vehicle_id' => $validated['vehicle_id'],
                'service_date' => $validated['service_date'],
                'service_type' => $validated['service_type'],
                'description' => $validated['description'],
                'cost' => $validated['cost'],
            ]);

            // Log successful creation
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_success',
                'table_name' => 'service_logs',
                'record_id' => $serviceLog->id,
                'description' => 'Created service log ID: ' . $serviceLog->id . 
                               ' for vehicle ' . $serviceLog->vehicle_id . 
                               ' (Type: ' . $serviceLog->service_type . 
                               ', Date: ' . $serviceLog->service_date . 
                               ', Cost: ' . number_format($serviceLog->cost, 2) . ')'
            ]);

            session()->flash('message', 'Service log created successfully!');
            $this->redirect(route('service-logs.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_failed',
                'table_name' => 'service_logs',
                'record_id' => null,
                'description' => 'Failed to create service log: ' . $e->getMessage() . 
                               ' | Input data: ' . json_encode([
                                    'vehicle_id' => $this->vehicle_id,
                                    'service_date' => $this->service_date,
                                    'service_type' => $this->service_type,
                                    'cost' => $this->cost
                                ])
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to create service log: ' . $e->getMessage(),
                'type' => 'error'
            ]);
            
            // Re-throw the exception for Laravel's error handling
            throw $e;
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Service Logs', 'url' => route('service-logs.index')],
            ['text' => 'Create Service Log', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Service Log</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new service log entry</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <form wire:submit="save">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Vehicle Selection -->
                    <div class="sm:col-span-2">
                        <label for="vehicle_id" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Vehicle</label>
                        <select
                            wire:model="vehicle_id" 
                            id="vehicle_id" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                            <option value="">Select a vehicle</option>
                            @foreach(App\Models\Vehicle::all() as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->license_plate }}</option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Service Date -->
                    <div>
                        <label for="service_date" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Service Date</label>
                        <input 
                            wire:model="service_date" 
                            type="date" 
                            id="service_date" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('service_date') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Service Type -->
                    <div>
                        <label for="service_type" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Service Type</label>
                        <input 
                            wire:model="service_type" 
                            type="text" 
                            id="service_type" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('service_type') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Cost -->
                    <div>
                        <label for="cost" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Cost</label>
                        <input 
                            wire:model="cost" 
                            type="number" 
                            step="0.01"
                            min="0"
                            id="cost" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('cost') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Description -->
                    <div class="sm:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Description</label>
                        <textarea 
                            wire:model="description" 
                            id="description" 
                            rows="3"
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"></textarea>
                        @error('description') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <div class="flex justify-end mt-6 space-x-4">
                    <a 
                        href="{{ route('service-logs.index') }}" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-white bg-white dark:bg-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        wire:navigate>
                        Cancel
                    </a>
                    
                    <button 
                        type="submit" 
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg wire:loading.remove wire:target="save" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Create Service Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>