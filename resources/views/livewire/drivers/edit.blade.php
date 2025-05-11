<?php

use Livewire\Volt\Component;
use App\Models\Driver;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public Driver $driver;
    public string $name = '';
    public string $license_number = '';
    public string $status = 'available';
    public string $password = '';
    public string $password_confirmation = '';
    public string $previousStatus = '';

    public function mount($id)
    {
        try {
            $this->driver = Driver::findOrFail($id);
            $this->name = $this->driver->name;
            $this->license_number = $this->driver->license_number;
            $this->status = $this->driver->status;
            $this->previousStatus = $this->status;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'view_edit',
                'table_name' => 'drivers',
                'record_id' => $this->driver->id,
                'description' => 'Viewing edit form for driver: ' . $this->driver->name . 
                               ' (ID: ' . $this->driver->id . ') | Current status: ' . $this->status
            ]);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'drivers',
                'record_id' => $id,
                'description' => 'Failed to load driver for editing: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to load driver: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:255', 'unique:drivers,license_number,'.$this->driver->id],
            'status' => ['required', 'in:available,on_duty'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function save()
    {
        try {
            $validated = $this->validate();

            // Log before update
            $changes = [
                'name' => $this->name !== $this->driver->name ? [$this->driver->name, $validated['name']] : null,
                'license_number' => $this->license_number !== $this->driver->license_number ? [$this->driver->license_number, $validated['license_number']] : null,
                'status' => $this->status !== $this->previousStatus ? [$this->previousStatus, $validated['status']] : null,
                'password' => !empty($validated['password']) ? ['******', '******'] : null
            ];

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_attempt',
                'table_name' => 'drivers',
                'record_id' => $this->driver->id,
                'description' => 'Attempting to update driver: ' . $this->driver->name . 
                               ' (ID: ' . $this->driver->id . ') | Changes: ' . json_encode(array_filter($changes))
            ]);

            $updateData = [
                'name' => $validated['name'],
                'license_number' => $validated['license_number'],
                'status' => $validated['status'],
            ];

            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $this->driver->update($updateData);

            // Log successful update
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_success',
                'table_name' => 'drivers',
                'record_id' => $this->driver->id,
                'description' => 'Successfully updated driver: ' . $this->driver->name . 
                               ' (ID: ' . $this->driver->id . ') | Changes applied: ' . json_encode(array_filter($changes))
            ]);

            session()->flash('message', 'Driver updated successfully!');
            $this->redirect(route('drivers.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_failed',
                'table_name' => 'drivers',
                'record_id' => $this->driver->id,
                'description' => 'Failed to update driver: ' . $this->driver->name . 
                               ' (ID: ' . $this->driver->id . ') | Error: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to update driver: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Drivers', 'url' => route('drivers.index')],
            ['text' => 'Edit Driver', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Driver</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update driver details</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <form wire:submit="save">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Name -->
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Full Name</label>
                        <input 
                            wire:model="name" 
                            type="text" 
                            id="name" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('name') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- License Number -->
                    <div class="sm:col-span-2">
                        <label for="license_number" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">License Number</label>
                        <input 
                            wire:model="license_number" 
                            type="text" 
                            id="license_number" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('license_number') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Status -->
                    <div class="sm:col-span-2">
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Status</label>
                        <select 
                            wire:model="status" 
                            id="status" 
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                            <option value="available" class="bg-white dark:bg-zinc-800">Available</option>
                            <option value="on_duty" class="bg-white dark:bg-zinc-800">On duty</option>
                        </select>
                        @error('status') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                </div>
                
                <div class="flex justify-end mt-6 space-x-4">
                    <a 
                        href="{{ route('drivers.index') }}" 
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
                        Update Driver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>