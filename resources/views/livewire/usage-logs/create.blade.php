<?php

use Livewire\Volt\Component;
use App\Models\UsageLog;
use App\Models\Booking;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public string $booking_id = '';
    public string $start_km = '';
    public string $end_km = '';
    public float $fuel_used = 0;
    public ?string $notes = '';
    public $selected_booking = null;

    protected function rules()
    {
        return [
            'booking_id' => ['required', 'exists:bookings,id'],
            'start_km' => ['required', 'integer', 'min:0'],
            'end_km' => ['required', 'integer', 'min:0', 'gte:start_km'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function updatedBookingId($value)
    {
        try {
            $this->reset('selected_booking', 'start_km', 'end_km', 'fuel_used');
            
            if ($value) {
                $this->selected_booking = Booking::with('vehicle')->find($value);
                
                if ($this->selected_booking?->vehicle) {
                    $this->start_km = $this->selected_booking->vehicle->current_mileage ?? '';
                    
                    SystemLog::create([
                        'user_id' => Auth::id(),
                        'action' => 'vehicle_mileage_loaded',
                        'table_name' => 'vehicles',
                        'record_id' => $this->selected_booking->vehicle->id,
                        'description' => 'Loaded current mileage (' . $this->start_km . 
                                       ' km) for vehicle ID: ' . $this->selected_booking->vehicle->id
                    ]);
                }
            }
            
            $this->calculateFuelUsed();

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'usage_logs',
                'record_id' => null,
                'description' => 'Failed to load booking details: ' . $e->getMessage()
            ]);
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['start_km', 'end_km'])) {
            $this->calculateFuelUsed();
        }
    }

    protected function calculateFuelUsed()
    {
        try {
            if ($this->selected_booking && 
                $this->selected_booking->vehicle && 
                is_numeric($this->start_km) && 
                is_numeric($this->end_km) &&
                $this->end_km > $this->start_km &&
                $this->selected_booking->vehicle->fuel_consumption > 0) {
                
                $distance = $this->end_km - $this->start_km;
                $this->fuel_used = round($distance * $this->selected_booking->vehicle->fuel_consumption, 2);

                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'fuel_calculated',
                    'table_name' => 'usage_logs',
                    'record_id' => null,
                    'description' => 'Calculated fuel usage: ' . $this->fuel_used . 
                                   ' liters for ' . $distance . ' km (Vehicle ID: ' . 
                                   $this->selected_booking->vehicle->id . ')'
                ]);
            } else {
                $this->fuel_used = 0;
            }
        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'usage_logs',
                'record_id' => null,
                'description' => 'Failed to calculate fuel usage: ' . $e->getMessage()
            ]);
        }
    }

    public function save()
    {
        try {
            $validated = $this->validate();

            // Log before creation
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_attempt',
                'table_name' => 'usage_logs',
                'record_id' => null,
                'description' => 'Attempting to create usage log for booking ID: ' . $validated['booking_id'] .
                               ' | Start KM: ' . $validated['start_km'] .
                               ' | End KM: ' . $validated['end_km'] .
                               ' | Fuel Used: ' . $this->fuel_used
            ]);

            $usageLog = UsageLog::create([
                'booking_id' => $validated['booking_id'],
                'start_km' => $validated['start_km'],
                'end_km' => $validated['end_km'],
                'fuel_used' => $this->fuel_used,
                'notes' => $validated['notes'],
            ]);

            if ($this->selected_booking?->vehicle) {
                $this->selected_booking->vehicle->update([
                    'current_mileage' => $validated['end_km']
                ]);

                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'mileage_updated',
                    'table_name' => 'vehicles',
                    'record_id' => $this->selected_booking->vehicle->id,
                    'description' => 'Updated mileage from ' . $validated['start_km'] . 
                                   ' to ' . $validated['end_km'] . ' km for vehicle ID: ' . 
                                   $this->selected_booking->vehicle->id
                ]);
            }

            // Log successful creation
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_success',
                'table_name' => 'usage_logs',
                'record_id' => $usageLog->id,
                'description' => 'Created usage log ID: ' . $usageLog->id . 
                               ' | Booking: ' . $usageLog->booking_id .
                               ' | Distance: ' . ($usageLog->end_km - $usageLog->start_km) . ' km' .
                               ' | Fuel: ' . $usageLog->fuel_used . ' liters'
            ]);

            session()->flash('message', 'Usage log created successfully!');
            $this->redirect(route('usage-logs.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_failed',
                'table_name' => 'usage_logs',
                'record_id' => null,
                'description' => 'Failed to create usage log: ' . $e->getMessage() .
                               ' | Input: ' . json_encode([
                                    'booking_id' => $this->booking_id,
                                    'start_km' => $this->start_km,
                                    'end_km' => $this->end_km,
                                    'fuel_used' => $this->fuel_used
                                ])
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to create usage log: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Usage Logs', 'url' => route('usage-logs.index')],
            ['text' => 'Create Usage Log', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Usage Log</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new usage log entry</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <form wire:submit="save">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Booking Selection -->
                    <div class="sm:col-span-2">
                        <label for="booking_id" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Booking</label>
                        <select
                            wire:model.live="booking_id"
                            id="booking_id" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                            <option value="">Select a booking</option>
                            @foreach(App\Models\Booking::with('vehicle')->get() as $booking)
                                <option value="{{ $booking->id }}">
                                    Booking #{{ $booking->id }} ({{ $booking->vehicle->license_plate ?? 'No Vehicle' }})
                                </option>
                            @endforeach
                        </select>
                        @error('booking_id') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        
                        @if($selected_booking && $selected_booking->vehicle)
                            <div class="mt-2 p-2 bg-gray-50 dark:bg-zinc-800 rounded-md">
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    <strong>Vehicle:</strong> {{ $selected_booking->vehicle->make }} {{ $selected_booking->vehicle->model }} 
                                    ({{ $selected_booking->vehicle->license_plate }})
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    <strong>Fuel Consumption:</strong> {{ $selected_booking->vehicle->fuel_consumption ?? 'N/A' }} L/100km
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    <strong>Current Mileage:</strong> {{ $selected_booking->vehicle->current_mileage ?? 'N/A' }} km
                                </p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Start KM -->
                    <div>
                        <label for="start_km" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Start Kilometer</label>
                        <input 
                            wire:model="start_km" 
                            type="number" 
                            id="start_km" 
                            min="0"
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('start_km') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- End KM -->
                    <div>
                        <label for="end_km" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">End Kilometer</label>
                        <input 
                            wire:model="end_km" 
                            type="number" 
                            id="end_km" 
                            min="0"
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('end_km') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Fuel Used -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Fuel Used (Liters)</label>
                        <div class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 text-gray-700 dark:text-white sm:text-sm">
                            {{ number_format($fuel_used, 2) }}
                        </div>
                        @if($selected_booking && $selected_booking->vehicle && $fuel_used > 0)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Calculated from {{ $end_km - $start_km }} km × {{ $selected_booking->vehicle->fuel_consumption }} L/100km
                            </p>
                        @endif
                    </div>
                    
                    <!-- Notes -->
                    <div class="sm:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Notes</label>
                        <textarea 
                            wire:model="notes" 
                            id="notes" 
                            rows="3"
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"></textarea>
                        @error('notes') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>
                
                <div class="flex justify-end mt-6 space-x-4">
                    <a 
                        href="{{ route('usage-logs.index') }}" 
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
                        Create Usage Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>