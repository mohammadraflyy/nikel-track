<?php

use Livewire\Volt\Component;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\User;
use App\Models\Booking;
use App\Models\Approval;
use App\Models\SystemLog;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $vehicle_id = '';
    public string $driver_id = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $purpose = '';
    public string $approver_level1_id = '';
    public string $approver_level2_id = '';
    public $availableVehicles = [];
    public $availableDrivers = [];
    public $level1Approvers = [];
    public $level2Approvers = [];

    protected function rules()
    {
        return [
            'vehicle_id' => [
                'required',
                'exists:vehicles,id',
                function ($attribute, $value, $fail) {
                    if ($this->start_date && $this->end_date) {
                        $hasConflict = Booking::where('vehicle_id', $value)
                            ->where(function ($query) {
                                $query->whereBetween('start_date', [$this->start_date, $this->end_date])
                                      ->orWhereBetween('end_date', [$this->start_date, $this->end_date])
                                      ->orWhere(function ($query) {
                                          $query->where('start_date', '<=', $this->start_date)
                                                ->where('end_date', '>=', $this->end_date);
                                      });
                            })
                            ->where('status', '!=', 'rejected')
                            ->exists();

                        if ($hasConflict) {
                            SystemLog::create([
                                'user_id' => auth()->id(),
                                'action' => 'validation_error',
                                'table_name' => 'bookings',
                                'record_id' => null,
                                'description' => 'Vehicle conflict detected during booking creation. Vehicle ID: '.$value
                            ]);
                            $fail('The selected vehicle is already booked during the selected date range.');
                        }
                    }
                }
            ],
            'driver_id' => [
                'required',
                'exists:drivers,id',
                function ($attribute, $value, $fail) {
                    if ($this->start_date && $this->end_date) {
                        $hasConflict = Booking::where('driver_id', $value)
                            ->where(function ($query) {
                                $query->whereBetween('start_date', [$this->start_date, $this->end_date])
                                      ->orWhereBetween('end_date', [$this->start_date, $this->end_date])
                                      ->orWhere(function ($query) {
                                          $query->where('start_date', '<=', $this->start_date)
                                                ->where('end_date', '>=', $this->end_date);
                                      });
                            })
                            ->where('status', '!=', 'rejected')
                            ->exists();

                        if ($hasConflict) {
                            SystemLog::create([
                                'user_id' => auth()->id(),
                                'action' => 'validation_error',
                                'table_name' => 'bookings',
                                'record_id' => null,
                                'description' => 'Driver conflict detected during booking creation. Driver ID: '.$value
                            ]);
                            $fail('The selected driver is already booked during the selected date range.');
                        }
                    }
                }
            ],
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'purpose' => 'required|string|max:500',
            'approver_level1_id' => [
                'required',
                'different:approver_level2_id',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $approver = User::find($value);
                    if (!$approver || !$approver->hasRole('approver_level1')) {
                        SystemLog::create([
                            'user_id' => auth()->id(),
                            'action' => 'validation_error',
                            'table_name' => 'bookings',
                            'record_id' => null,
                            'description' => 'Invalid Level 1 approver selected: '.$value
                        ]);
                        $fail('The selected first approver must have the approver_level1 role.');
                    }
                }
            ],
            'approver_level2_id' => [
                'required',
                'different:approver_level1_id',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $approver = User::find($value);
                    if (!$approver || !$approver->hasRole('approver_level2')) {
                        SystemLog::create([
                            'user_id' => auth()->id(),
                            'action' => 'validation_error',
                            'table_name' => 'bookings',
                            'record_id' => null,
                            'description' => 'Invalid Level 2 approver selected: '.$value
                        ]);
                        $fail('The selected second approver must have the approver_level2 role.');
                    }
                }
            ],
        ];
    }

    public function mount()
    {
        try {
            $this->availableVehicles = Vehicle::where('status', 'available')->get();
            $this->availableDrivers = Driver::where('status', 'available')->get();
            $this->level1Approvers = User::role('approver_level1')->get();
            $this->level2Approvers = User::role('approver_level2')->get();

            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'page_load',
                'table_name' => 'bookings',
                'record_id' => null,
                'description' => 'Loaded booking creation form'
            ]);
        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'error',
                'table_name' => 'bookings',
                'record_id' => null,
                'description' => 'Failed to load booking creation form: '.$e->getMessage()
            ]);
        }
    }

    public function save()
    {
        try {
            $validated = $this->validate();

            DB::transaction(function () use ($validated) {
                $booking = Booking::create([
                    'user_id' => auth()->id(),
                    'vehicle_id' => $validated['vehicle_id'],
                    'driver_id' => $validated['driver_id'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'purpose' => $validated['purpose'],
                    'status' => 'pending',
                ]);

                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'create',
                    'table_name' => 'bookings',
                    'record_id' => $booking->id,
                    'description' => 'Created new booking for vehicle '.$validated['vehicle_id'].' and driver '.$validated['driver_id']
                ]);

                $approval1 = Approval::create([
                    'booking_id' => $booking->id,
                    'approver_id' => $validated['approver_level1_id'],
                    'level' => 1,
                    'status' => 'pending'
                ]);

                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'create',
                    'table_name' => 'approvals',
                    'record_id' => $approval1->id,
                    'description' => 'Created Level 1 approval for booking '.$booking->id
                ]);

                $approval2 = Approval::create([
                    'booking_id' => $booking->id,
                    'approver_id' => $validated['approver_level2_id'],
                    'level' => 2,
                    'status' => 'pending'
                ]);

                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'create',
                    'table_name' => 'approvals',
                    'record_id' => $approval2->id,
                    'description' => 'Created Level 2 approval for booking '.$booking->id
                ]);

                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'update',
                    'table_name' => 'vehicles',
                    'record_id' => $validated['vehicle_id'],
                    'description' => 'Marked vehicle as booked for booking '.$booking->id
                ]);

                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'update',
                    'table_name' => 'drivers',
                    'record_id' => $validated['driver_id'],
                    'description' => 'Marked driver as booked for booking '.$booking->id
                ]);
            });

            $this->redirect(route('bookings.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'error',
                'table_name' => 'bookings',
                'record_id' => null,
                'description' => 'Failed to create booking: '.$e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to create booking: '.$e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Bookings', 'url' => route('bookings.index')],
            ['text' => 'Create', 'url' => '#']
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">New Vehicle Booking</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Request a vehicle for your needs</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <form wire:submit="save">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Vehicle Selection -->
                    <div>
                        <label for="vehicle_id" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Select Vehicle</label>
                        <select 
                            wire:model="vehicle_id" 
                            id="vehicle_id"
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                            <option value="" class="bg-white dark:bg-zinc-800">Choose a vehicle</option>
                            @foreach($this->availableVehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" class="bg-white dark:bg-zinc-800">
                                    {{ $vehicle->license_plate }} ({{ $vehicle->type }})
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Driver Selection -->
                    <div>
                        <label for="driver_id" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Select Driver</label>
                        <select 
                            wire:model="driver_id" 
                            id="driver_id"
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                            <option value="" class="bg-white dark:bg-zinc-800">Choose a driver</option>
                            @foreach($this->availableDrivers as $driver)
                                <option value="{{ $driver->id }}" class="bg-white dark:bg-zinc-800">
                                    {{ $driver->name }} ({{ $driver->license_number }})
                                </option>
                            @endforeach
                        </select>
                        @error('driver_id') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Date Range -->
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Start Date</label>
                        <input 
                            wire:model="start_date" 
                            type="date" 
                            id="start_date"
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('start_date') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">End Date</label>
                        <input 
                            wire:model="end_date" 
                            type="date" 
                            id="end_date"
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 px-3 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('end_date') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label for="approver_level1_id" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">First Approver</label>
                        <select 
                            wire:model="approver_level1_id" 
                            id="approver_level1_id"
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                            <option value="" class="bg-white dark:bg-zinc-800">Select first approver</option>
                            @foreach($this->level1Approvers as $approver)
                                <option value="{{ $approver->id }}" class="bg-white dark:bg-zinc-800">
                                    {{ $approver->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('approver_level1_id') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <!-- Second Approver -->
                    <div>
                        <label for="approver_level2_id" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Second Approver</label>
                        <select 
                            wire:model="approver_level2_id" 
                            id="approver_level2_id"
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                            <option value="" class="bg-white dark:bg-zinc-800">Select second approver</option>
                            @foreach($this->level2Approvers as $approver)
                                <option value="{{ $approver->id }}" class="bg-white dark:bg-zinc-800">
                                    {{ $approver->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('approver_level2_id') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Purpose -->
                    <div class="md:col-span-2">
                        <label for="purpose" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Purpose of Booking</label>
                        <textarea 
                            wire:model="purpose" 
                            id="purpose"
                            rows="3"
                            class="block w-full rounded-md border border-gray-300 p-3 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            placeholder="Describe why you need this vehicle..."
                            required></textarea>
                        @error('purpose') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <div class="flex justify-end mt-6 space-x-4">
                    <a 
                        href="{{ route('bookings.index') }}" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-white bg-white dark:bg-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        wire:navigate>
                        Cancel
                    </a>
                    
                    <button 
                        type="submit" 
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg wire:loading.remove wire:target="save" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Submit Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>