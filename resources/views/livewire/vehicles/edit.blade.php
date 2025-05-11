<?php

use Livewire\Volt\Component;
use App\Models\Vehicle;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public Vehicle $vehicle;
    public string $license_plate = '';
    public string $type = 'orang';
    public string $fuel_consumption = '';
    public string $status = 'available';
    
    public function mount($id)
    {
        try {
            $this->vehicle = Vehicle::findOrFail($id);
            $this->license_plate = $this->vehicle->license_plate;
            $this->type = $this->vehicle->type;
            $this->fuel_consumption = $this->vehicle->fuel_consumption;
            $this->status = $this->vehicle->status;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'view_edit',
                'table_name' => 'vehicles',
                'record_id' => $this->vehicle->id,
                'description' => 'Viewing edit form for vehicle ID: ' . $this->vehicle->id . 
                               ' (License Plate: ' . $this->vehicle->license_plate . ')'
            ]);
        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'vehicles',
                'record_id' => $id,
                'description' => 'Failed to load vehicle for editing: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to load vehicle: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
    
    protected function rules()
    {
        return [
            'license_plate' => 'required|string|max:15|unique:vehicles,license_plate,'.$this->vehicle->id,
            'type' => 'required|in:orang,barang',
            'fuel_consumption' => 'required|numeric|min:0.1|max:50',
            'status' => 'required|in:available,on_duty,service',
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
                'table_name' => 'vehicles',
                'record_id' => $this->vehicle->id,
                'description' => 'Attempting to update vehicle ID: ' . $this->vehicle->id . 
                                 ' | Old values: ' . json_encode([
                                    'license_plate' => $this->vehicle->license_plate,
                                    'type' => $this->vehicle->type,
                                    'fuel_consumption' => $this->vehicle->fuel_consumption,
                                    'status' => $this->vehicle->status
                                 ]) . 
                                 ' | New values: ' . json_encode($validated)
            ]);

            $this->vehicle->update($validated);

            // Log successful update
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_success',
                'table_name' => 'vehicles',
                'record_id' => $this->vehicle->id,
                'description' => 'Successfully updated vehicle ID: ' . $this->vehicle->id . 
                               ' (License Plate: ' . $this->vehicle->license_plate . ')'
            ]);

            session()->flash('message', 'Vehicle updated successfully!');
            $this->redirect(route('vehicles.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_failed',
                'table_name' => 'vehicles',
                'record_id' => $this->vehicle->id,
                'description' => 'Failed to update vehicle ID: ' . $this->vehicle->id . 
                               ' - Error: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to update vehicle: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Vehicle Fleet', 'url' => route('vehicles.index')],
            ['text' => 'Edit Vehicle', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Vehicle</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update vehicle details</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <form wire:submit="save">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- License Plate -->
                    <div>
                        <label for="license_plate" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">License Plate</label>
                        <input 
                            wire:model="license_plate" 
                            type="text" 
                            id="license_plate" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('license_plate') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Vehicle Type -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Vehicle Type</label>
                        <select 
                            wire:model="type" 
                            id="type" 
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                            <option value="orang" class="bg-white dark:bg-zinc-800">Passenger Vehicle</option>
                            <option value="barang" class="bg-white dark:bg-zinc-800">Cargo Vehicle</option>
                        </select>
                        @error('type') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Fuel Consumption -->
                    <div>
                        <label for="fuel_consumption" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Fuel Consumption (L/km)</label>
                        <input 
                            wire:model="fuel_consumption" 
                            type="number" 
                            step="0.01" 
                            id="fuel_consumption" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('fuel_consumption') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Status</label>
                        <select 
                            wire:model="status" 
                            id="status" 
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                            <option value="available" class="bg-white dark:bg-zinc-800">Available</option>
                            <option value="on_duty" class="bg-white dark:bg-zinc-800">On Duty</option>
                            <option value="service" class="bg-white dark:bg-zinc-800">In Service</option>
                        </select>
                        @error('status') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <div class="flex justify-end mt-6 space-x-4">
                    <a 
                        href="{{ route('vehicles.index') }}" 
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
                        Update Vehicle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>