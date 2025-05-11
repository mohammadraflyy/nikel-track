<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Driver;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $drivers = [];
    public string $search = '';
    public $driverToDelete = null;
    public bool $showDeleteModal = false;

    public function drivers()
    {
        try {
            $query = Driver::query()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->latest();

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'page_load',
                'table_name' => 'drivers',
                'record_id' => null,
                'description' => 'Viewed drivers management page'
            ]);

            if ($this->search) {
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'search',
                    'table_name' => 'drivers',
                    'record_id' => null,
                    'description' => 'Searched drivers with query: ' . $this->search
                ]);
            }

            return $query->paginate(10);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'drivers',
                'record_id' => null,
                'description' => 'Failed to load drivers: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to load drivers: ' . $e->getMessage(),
                'type' => 'error'
            ]);

            return Driver::query()->paginate(10);
        }
    }

    public function confirmDelete($id)
    {
        try {
            $this->driverToDelete = $id;
            $this->showDeleteModal = true;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_initiated',
                'table_name' => 'drivers',
                'record_id' => $id,
                'description' => 'Initiated deletion of driver ID: ' . $id
            ]);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'drivers',
                'record_id' => $id,
                'description' => 'Failed to initiate driver deletion: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to initiate deletion: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    public function deleteDriver()
    {
        try {
            if (!$this->driverToDelete) {
                throw new \Exception('No driver selected for deletion');
            }

            $driver = Driver::findOrFail($this->driverToDelete);

            // Log before deletion
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_attempt',
                'table_name' => 'drivers',
                'record_id' => $driver->id,
                'description' => 'Attempting to delete driver: ' . $driver->name . ' (ID: ' . $driver->id . ')'
            ]);

            $driver->delete();

            // Log successful deletion
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_success',
                'table_name' => 'drivers',
                'record_id' => $driver->id,
                'description' => 'Successfully deleted driver: ' . $driver->name . ' (ID: ' . $driver->id . ')'
            ]);

            $this->showDeleteModal = false;
            $this->driverToDelete = null;
            session()->flash('message', 'Driver deleted successfully!');

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_failed',
                'table_name' => 'drivers',
                'record_id' => $this->driverToDelete,
                'description' => 'Failed to delete driver: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to delete driver: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Driver Management', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Driver Management</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage system drivers</p>
            </div>
            <div>
                <a href="{{ route('drivers.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Driver
                </a>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <div class="mb-6">
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
                        placeholder="Search drivers...">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">License Number</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($this->drivers() as $driver)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $driver->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $driver->license_number }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="uppercase px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $driver->status === 'available' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100' }}">
                                        {{ ucfirst($driver->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a 
                                            href="{{ route('drivers.edit', $driver->id) }}" 
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <button 
                                            wire:click="confirmDelete({{ $driver->id }})" 
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
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center p-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No drivers found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new driver to get started</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->drivers()->links() }}
            </div>
        </div>
    </div>

    <flux:modal wire:model="showDeleteModal">
        <flux:heading>
            Delete driver
        </flux:heading>

        <flux:text>
            Are you sure you want to delete this driver? This action cannot be undone.
        </flux:text>

        <div class="flex gap-2 mt-4">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" type="button" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="button" wire:click="deleteDriver">Delete driver</flux:button>
        </div>
    </flux:modal>

    @if (session()->has('message'))
        <x-notification message="{{ session('message') }}" type="success" />
    @endif
</div>