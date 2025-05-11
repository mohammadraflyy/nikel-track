<?php

use Livewire\Volt\Component;
use App\Models\FuelLog;
use App\Models\Vehicle;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public FuelLog $fuelLog;
    public string $vehicle_id;
    public string $amount;
    public string $log_date;
    public ?string $notes = null;

    public function mount($id)
    {
        try {
            $this->fuelLog = FuelLog::findOrFail($id);
            $this->vehicle_id = $this->fuelLog->vehicle_id;
            $this->amount = $this->fuelLog->amount;
            $this->log_date = $this->fuelLog->log_date->format('Y-m-d');
            $this->notes = $this->fuelLog->notes;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'view_edit',
                'table_name' => 'fuel_logs',
                'record_id' => $this->fuelLog->id,
                'description' => 'Viewing edit form for fuel log ID: ' . $this->fuelLog->id . 
                               ' (Vehicle: ' . $this->fuelLog->vehicle_id . 
                               ', Date: ' . $this->log_date . 
                               ', Amount: ' . $this->amount . ')'
            ]);
        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'fuel_logs',
                'record_id' => $id,
                'description' => 'Failed to load fuel log for editing: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to load fuel log: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    protected function rules()
    {
        return [
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'log_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        try {
            $validated = $this->validate();

            // Log before update with old values
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_attempt',
                'table_name' => 'fuel_logs',
                'record_id' => $this->fuelLog->id,
                'description' => 'Attempting to update fuel log ID: ' . $this->fuelLog->id . 
                               ' | Old values: ' . json_encode([
                                    'vehicle_id' => $this->fuelLog->vehicle_id,
                                    'amount' => $this->fuelLog->amount,
                                    'log_date' => $this->fuelLog->log_date,
                                    'notes' => $this->fuelLog->notes
                                ]) . 
                               ' | New values: ' . json_encode($validated)
            ]);

            $this->fuelLog->update([
                'vehicle_id' => $validated['vehicle_id'],
                'amount' => $validated['amount'],
                'log_date' => $validated['log_date'],
                'notes' => $validated['notes'],
            ]);

            // Log successful update
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_success',
                'table_name' => 'fuel_logs',
                'record_id' => $this->fuelLog->id,
                'description' => 'Successfully updated fuel log ID: ' . $this->fuelLog->id . 
                               ' (Vehicle: ' . $validated['vehicle_id'] . 
                               ', Date: ' . $validated['log_date'] . 
                               ', Amount: ' . $validated['amount'] . ')'
            ]);

            session()->flash('message', 'Fuel log updated successfully!');
            $this->redirect(route('fuel-logs.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_failed',
                'table_name' => 'fuel_logs',
                'record_id' => $this->fuelLog->id,
                'description' => 'Failed to update fuel log ID: ' . $this->fuelLog->id . 
                               ' - Error: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to update fuel log: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Fuel Logs', 'url' => route('fuel-logs.index')],
            ['text' => 'Edit Fuel Log', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Fuel Log</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update fuel log details</p>
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
                                <option value="{{ $vehicle->id }}" @selected($vehicle->id == $fuelLog->vehicle_id)>
                                    {{ $vehicle->fuel_consumption }} km/L ({{ $vehicle->license_plate }})
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Amount (Liters)</label>
                        <input 
                            wire:model="amount" 
                            type="number" 
                            step="0.01"
                            min="0"
                            id="amount" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('amount') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Log Date -->
                    <div>
                        <label for="log_date" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Date</label>
                        <input 
                            wire:model="log_date" 
                            type="date" 
                            id="log_date" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('log_date') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Notes -->
                    <div class="sm:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Notes</label>
                        <textarea 
                            wire:model="notes" 
                            id="notes" 
                            rows="3"
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">{{ $notes }}</textarea>
                        @error('notes') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <div class="flex justify-end mt-6 space-x-4">
                    <a 
                        href="{{ route('fuel-logs.index') }}" 
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
                        Update Fuel Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>