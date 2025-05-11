<?php

use Livewire\Volt\Component;
use App\Models\Driver;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public string $name = '';
    public string $license_number = '';
    public string $status = 'available';
    public string $password = '';
    public string $password_confirmation = '';

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'license_number' => ['required', 'string', 'max:255', 'unique:drivers'],
            'status' => ['required', 'in:available,on_duty'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function save()
    {
        try {
            $validated = $this->validate();

            // Log before creation
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_attempt',
                'table_name' => 'drivers',
                'record_id' => null,
                'description' => 'Attempting to create new driver: ' . $validated['name'] . 
                               ' | License: ' . $validated['license_number']
            ]);

            $driver = Driver::create([
                'name' => $validated['name'],
                'license_number' => $validated['license_number'],
                'status' => $validated['status'],
                'password' => Hash::make($validated['password']),
            ]);

            // Log successful creation
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_success',
                'table_name' => 'drivers',
                'record_id' => $driver->id,
                'description' => 'Created new driver: ' . $driver->name . 
                               ' (ID: ' . $driver->id . ') | License: ' . $driver->license_number
            ]);

            session()->flash('message', 'Driver created successfully!');
            $this->redirect(route('drivers.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_failed',
                'table_name' => 'drivers',
                'record_id' => null,
                'description' => 'Failed to create driver: ' . $e->getMessage() . 
                               ' | Input: ' . json_encode([
                                    'name' => $this->name,
                                    'license_number' => $this->license_number
                                ])
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to create driver: ' . $e->getMessage(),
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
            ['text' => 'Create Driver', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Driver</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new driver to the system</p>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Create Driver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>