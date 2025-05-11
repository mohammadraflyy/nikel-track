<?php

use Livewire\Volt\Component;
use App\Models\ServiceLog;
use App\Models\Vehicle;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public ServiceLog $serviceLog;
    public string $vehicle_id;
    public string $service_date;
    public string $service_type;
    public ?string $description;
    public string $cost;

    public function mount($id)
    {
        try {
            $this->serviceLog = ServiceLog::findOrFail($id);
            $this->vehicle_id = $this->serviceLog->vehicle_id;
            $this->service_date = $this->serviceLog->service_date->format('Y-m-d');
            $this->service_type = $this->serviceLog->service_type;
            $this->description = $this->serviceLog->description;
            $this->cost = $this->serviceLog->cost;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'view_edit',
                'table_name' => 'service_logs',
                'record_id' => $this->serviceLog->id,
                'description' => 'Viewed service log for editing - ID: ' . $this->serviceLog->id . 
                               ' | Vehicle: ' . $this->vehicle_id .
                               ' | Type: ' . $this->service_type .
                               ' | Date: ' . $this->service_date
            ]);
        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'service_logs',
                'record_id' => $id,
                'description' => 'Failed to load service log for editing: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to load service log: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    protected function rules()
    {
        return [
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'service_date' => ['required', 'date'],
            'service_type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function save()
    {
        try {
            $validated = $this->validate();

            // Log before update
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_attempt',
                'table_name' => 'service_logs',
                'record_id' => $this->serviceLog->id,
                'description' => 'Attempting to update service log - ' .
                               'Old values: ' . json_encode([
                                   'vehicle_id' => $this->serviceLog->vehicle_id,
                                   'service_date' => $this->serviceLog->service_date,
                                   'service_type' => $this->serviceLog->service_type,
                                   'cost' => $this->serviceLog->cost,
                                   'description' => $this->serviceLog->description
                               ]) .
                               ' | New values: ' . json_encode($validated)
            ]);

            $this->serviceLog->update([
                'vehicle_id' => $validated['vehicle_id'],
                'service_date' => $validated['service_date'],
                'service_type' => $validated['service_type'],
                'description' => $validated['description'],
                'cost' => $validated['cost'],
            ]);

            // Log successful update
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_success',
                'table_name' => 'service_logs',
                'record_id' => $this->serviceLog->id,
                'description' => 'Successfully updated service log - ID: ' . $this->serviceLog->id .
                               ' | Vehicle: ' . $validated['vehicle_id'] .
                               ' | Type: ' . $validated['service_type'] .
                               ' | Date: ' . $validated['service_date'] .
                               ' | Cost: ' . number_format($validated['cost'], 2)
            ]);

            session()->flash('message', 'Service log updated successfully!');
            $this->redirect(route('service-logs.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_failed',
                'table_name' => 'service_logs',
                'record_id' => $this->serviceLog->id,
                'description' => 'Failed to update service log - ID: ' . $this->serviceLog->id .
                               ' | Error: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to update service log: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Service Logs', 'url' => route('service-logs.index')],
            ['text' => 'Edit Service Log', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Service Log</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update service log details</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <form wire:submit="save">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Vehicle -->
                    <div class="sm:col-span-2">
                        <label for="vehicle_id" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Vehicle</label>
                        <select 
                            wire:model="vehicle_id" 
                            id="vehicle_id" 
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                            @foreach(App\Models\Vehicle::all() as $vehicle)
                                <option value="{{ $vehicle->id }}" class="bg-white dark:bg-zinc-800"> {{ $vehicle->fuel_consumption }} ({{ $vehicle->license_plate }})</option>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Update Service Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>