<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Vehicle;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;
    
    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';
    public $vehicleToDelete = null;
    public $showDeleteModal = false;
    
    public function vehicles()
    {
        try {
            $query = Vehicle::query()
                ->withCount('bookings')
                ->when($this->search, fn($q) => $q->where('license_plate', 'like', "%{$this->search}%"))
                ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
                ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
                ->latest();

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'page_load',
                'table_name' => 'vehicles',
                'record_id' => null,
                'description' => 'Loaded vehicle management page'
            ]);
            
            if ($this->search || $this->statusFilter || $this->typeFilter) {
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'search',
                    'table_name' => 'vehicles',
                    'record_id' => null,
                    'description' => 'Searched vehicles with filters: ' . json_encode([
                        'search' => $this->search,
                        'status' => $this->statusFilter,
                        'type' => $this->typeFilter
                    ])
                ]);
            }

            return $query->paginate(10);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'vehicles',
                'record_id' => null,
                'description' => 'Failed to fetch vehicles: ' . $e->getMessage()
            ]);
            
            return Vehicle::query()->paginate(10);
        }
    }
    
    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'typeFilter']);
        $this->resetPage();
        
        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'filter_reset',
            'table_name' => 'vehicles',
            'record_id' => null,
            'description' => 'Reset all vehicle filters'
        ]);
    }
    
    public function confirmDelete($id)
    {
        try {
            $this->vehicleToDelete = $id;
            $this->showDeleteModal = true;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_initiated',
                'table_name' => 'vehicles',
                'record_id' => $id,
                'description' => 'Initiated deletion for vehicle ID: ' . $id
            ]);
        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'vehicles',
                'record_id' => $id,
                'description' => 'Failed to initiate deletion: ' . $e->getMessage()
            ]);
        }
    }
    
    public function deleteVehicle()
    {
        try {
            $vehicle = Vehicle::findOrFail($this->vehicleToDelete);
            
            // Log before deletion
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_attempt',
                'table_name' => 'vehicles',
                'record_id' => $vehicle->id,
                'description' => 'Attempting to delete vehicle ID: ' . $vehicle->id . 
                                 ' (License Plate: ' . $vehicle->license_plate . ')'
            ]);

            $vehicle->delete();
            
            // Log successful deletion
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_success',
                'table_name' => 'vehicles',
                'record_id' => $vehicle->id,
                'description' => 'Successfully deleted vehicle ID: ' . $vehicle->id . 
                               ' (License Plate: ' . $vehicle->license_plate . ')'
            ]);

            $this->showDeleteModal = false;
            $this->vehicleToDelete = null;
            session()->flash('message', 'Vehicle deleted successfully!');

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_failed',
                'table_name' => 'vehicles',
                'record_id' => $this->vehicleToDelete,
                'description' => 'Failed to delete vehicle: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to delete vehicle: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>
<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Vehicle Fleet', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Vehicle Fleet</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage all company vehicles</p>
            </div>
            <a 
                href="{{ route('vehicles.create') }}"
                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Vehicle
            </a>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-4">
                <!-- Search -->
                <div class="relative rounded-md shadow-sm">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input 
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Search vehicles..."
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-3 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                </div>
                
                <!-- Status Filter -->
                <select 
                    wire:model.live="statusFilter"
                    class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                    <option value="" class="bg-white dark:bg-zinc-800">All Statuses</option>
                    <option value="available" class="bg-white dark:bg-zinc-800">Available</option>
                    <option value="on_duty" class="bg-white dark:bg-zinc-800">On Duty</option>
                    <option value="service" class="bg-white dark:bg-zinc-800">In Service</option>
                </select>
                
                <!-- Type Filter -->
                <select 
                    wire:model.live="typeFilter"
                    class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                    <option value="" class="bg-white dark:bg-zinc-800">All Types</option>
                    <option value="orang" class="bg-white dark:bg-zinc-800">Passenger</option>
                    <option value="barang" class="bg-white dark:bg-zinc-800">Cargo</option>
                </select>
                
                <!-- Clear Filters -->
                <button 
                    wire:click="clearFilters"
                    class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-white bg-white dark:bg-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Clear Filters
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">License Plate</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fuel Consumption</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bookings</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($this->vehicles() as $vehicle)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $vehicle->license_plate }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $vehicle->type === 'orang' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100' : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-100' }}">
                                        {{ $vehicle->type === 'orang' ? 'Passenger' : 'Cargo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $vehicle->fuel_consumption }} km/L
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ match($vehicle->status) {
                                        'available' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
                                        'on_duty' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100',
                                        'service' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100',
                                    } }}">
                                        {{ ucfirst(str_replace('_', ' ', $vehicle->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $vehicle->bookings_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a 
                                            href="{{ route('vehicles.edit', $vehicle) }}"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                            wire:navigate>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <button 
                                            wire:click="confirmDelete({{ $vehicle->id }})"
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
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No vehicles found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new vehicle to get started</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->vehicles()->links() }}
            </div>
        </div>
    </div>

    <flux:modal wire:model="showDeleteModal">
        <flux:heading>
            Delete Vehicle
        </flux:heading>

        <flux:text>
            Are you sure you want to delete this vehicle? This action cannot be undone.
        </flux:text>

        <div class="flex gap-2 mt-4">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" type="button" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="button" wire:click="deleteVehicle">Delete vehicle</flux:button>
        </div>
    </flux:modal>

    @if (session()->has('message'))
        <x-notification message="{{ session('message') }}" type="success" />
    @endif
</div>