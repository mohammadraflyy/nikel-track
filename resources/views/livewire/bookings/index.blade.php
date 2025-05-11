<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Booking;
use App\Models\SystemLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public $bookingToCancel = null;
    public $showCancelModal = false;

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
        
        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'filter_reset',
            'table_name' => 'bookings',
            'record_id' => null,
            'description' => 'Reset all booking filters'
        ]);
    }

    public function bookings()
    {
        try {
            $query = Booking::query()
                ->with(['vehicle', 'driver', 'user'])
                ->when($this->search, fn($q) => $q->where('purpose', 'like', "%{$this->search}%"))
                ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
                ->when($this->dateFrom, fn($q) => $q->where('start_date', '>=', $this->dateFrom))
                ->when($this->dateTo, fn($q) => $q->where('end_date', '<=', $this->dateTo))
                ->latest();

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'page_load',
                'table_name' => 'bookings',
                'record_id' => null,
                'description' => 'Loaded booking page'
            ]);
            
            if ($this->search || $this->statusFilter || $this->dateFrom || $this->dateTo) {
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'search',
                    'table_name' => 'bookings',
                    'record_id' => null,
                    'description' => 'Searched bookings with filters: ' . json_encode([
                        'search' => $this->search,
                        'status' => $this->statusFilter,
                        'date_from' => $this->dateFrom,
                        'date_to' => $this->dateTo
                    ])
                ]);
            }

            return $query->paginate(10)
                ->through(function ($booking) {
                    $booking->start_date = \Carbon\Carbon::parse($booking->start_date);
                    $booking->end_date = \Carbon\Carbon::parse($booking->end_date);
                    return $booking;
                });

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'bookings',
                'record_id' => null,
                'description' => 'Failed to fetch bookings: ' . $e->getMessage()
            ]);
            
            return Booking::query()->paginate(10);
        }
    }

    public function confirmCancel($id)
    {
        try {
            $this->bookingToCancel = $id;
            $this->showCancelModal = true;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'cancel_initiated',
                'table_name' => 'bookings',
                'record_id' => $id,
                'description' => 'Initiated cancellation for booking ID: ' . $id
            ]);
        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'bookings',
                'record_id' => $id,
                'description' => 'Failed to initiate cancellation: ' . $e->getMessage()
            ]);
        }
    }

    public function cancelBooking()
    {
        try {
            $booking = Booking::findOrFail($this->bookingToCancel);
            
            // Log before cancellation
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'cancel_attempt',
                'table_name' => 'bookings',
                'record_id' => $booking->id,
                'description' => 'Attempting to cancel booking ID: ' . $booking->id . 
                               ' with status: ' . $booking->status
            ]);

            $booking->update(['status' => 'rejected']);
            
            // Update related resources
            if ($booking->vehicle) {
                $booking->vehicle->update(['status' => 'available']);
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'status_update',
                    'table_name' => 'vehicles',
                    'record_id' => $booking->vehicle->id,
                    'description' => 'Marked vehicle as available after booking cancellation. Booking ID: ' . $booking->id
                ]);
            }

            if ($booking->driver) {
                $booking->driver->update(['status' => 'available']);
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'status_update',
                    'table_name' => 'drivers',
                    'record_id' => $booking->driver->id,
                    'description' => 'Marked driver as available after booking cancellation. Booking ID: ' . $booking->id
                ]);
            }

            // Log successful cancellation
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'cancel_success',
                'table_name' => 'bookings',
                'record_id' => $booking->id,
                'description' => 'Successfully cancelled booking ID: ' . $booking->id
            ]);

            $this->showCancelModal = false;
            $this->bookingToCancel = null;
            session()->flash('message', 'Booking cancelled successfully!');

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'cancel_failed',
                'table_name' => 'bookings',
                'record_id' => $this->bookingToCancel,
                'description' => 'Failed to cancel booking: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to cancel booking: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Bookings', 'url' => '#'],
        ]" />
                
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Vehicle Bookings</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage all booking requests</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-6">
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
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 dark:bg-zinc-800 dark:text-white" 
                        placeholder="Search bookings...">
                </div>
                
                <!-- Status Filter -->
                <select 
                    wire:model.live="statusFilter" 
                    class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-3 px-3 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                    <option value="" class="bg-white dark:bg-zinc-800">All Statuses</option>
                    <option value="pending" class="bg-white dark:bg-zinc-800">Pending</option>
                    <option value="approved" class="bg-white dark:bg-zinc-800">Approved</option>
                    <option value="rejected" class="bg-white dark:bg-zinc-800">Rejected</option>
                </select>
                
                <!-- Date From -->
                <div class="relative rounded-md shadow-sm">
                    <label class="sr-only">From Date</label>
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <input 
                        wire:model.live="dateFrom" 
                        type="date" 
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 pl-10 py-3 focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                </div>
                
                <!-- Date To -->
                <div class="relative rounded-md shadow-sm">
                    <label class="sr-only">To Date</label>
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <input 
                        wire:model.live="dateTo" 
                        type="date" 
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 pl-10 py-3 focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vehicle</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Driver</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dates</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Purpose</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($this->bookings() as $booking)
                            <tr wire:key="{{ $booking->id }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $booking->vehicle->license_plate }}
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $booking->driver->name }}
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <div class="text-sm">
                                        {{ $booking->start_date->format('M d, Y') }}
                                        <span class="text-gray-400 dark:text-gray-500">→</span>
                                        {{ $booking->end_date->format('M d, Y') }}
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ Str::limit($booking->purpose, 30) }}
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @php
                                        $statusColors = [
                                            'approved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
                                            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
                                            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100'
                                        ];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$booking->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('bookings.show', $booking) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center p-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No bookings found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new booking to get started</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                {{ $this->bookings()->links() }}
            </div>
        </div>
    </div>

    <flux:modal wire:model="showCancelModal">
        <flux:heading>
            Cancel Booking
        </flux:heading>

        <flux:text>
            Are you sure you want to cancel this booking? This action cannot be undone.
        </flux:text>

        <div class="flex gap-2 mt-4">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" type="button" wire:click="$set('showCancelModal', false)">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="button" wire:click="cancelBooking">Cancel booking</flux:button>
        </div>
    </flux:modal>

    @if (session()->has('message'))
        <x-notification message="{{ session('message') }}" type="success" />
    @endif
</div>