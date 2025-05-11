<?php

use Livewire\Volt\Component;
use App\Models\Booking;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public Booking $booking;

    public function mount($id)
    {
        try {
            $this->booking = Booking::with(['vehicle', 'driver', 'user', 'approvals'])->find($id);
            
            if (!$this->booking) {
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'not_found',
                    'table_name' => 'bookings',
                    'record_id' => $id,
                    'description' => 'Attempted to access non-existent booking ID: ' . $id
                ]);
                abort(404);
            }

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'view',
                'table_name' => 'bookings',
                'record_id' => $this->booking->id,
                'description' => 'Viewed booking details for ID: ' . $this->booking->id . 
                               ' (Status: ' . $this->booking->status . ')'
            ]);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'bookings',
                'record_id' => $id ?? null,
                'description' => 'Failed to load booking view: ' . $e->getMessage()
            ]);
            abort(500);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Bookings', 'url' => route('bookings.index')],
            ['text' => 'Booking #' . $booking->id, 'url' => '#'],
        ]" />
                
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Booking Details</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Created on {{ date('M d, Y', strtotime($booking->created_at)) }}
                </p>
            </div>
            
            @if($booking->status === 'pending' && auth()->user()->can('approve', $booking))
                <div class="flex space-x-2">
                    <button wire:click="approveBooking" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Approve
                    </button>
                    <button wire:click="rejectBooking" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Reject
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg overflow-hidden">
        <!-- Status Header -->
        <div class="px-6 py-5 border-b border-gray-200 dark:border-zinc-700 flex items-center justify-between bg-gray-50 dark:bg-zinc-800">
            <div>
                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Booking #{{ $booking->id }}</h2>
                <div class="mt-1 flex items-center">
                    @php
                        $statusColors = [
                            'approved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
                            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
                            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100'
                        ];
                    @endphp
                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $statusColors[$booking->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100' }}">
                        {{ ucfirst($booking->status) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-6 py-5 grid grid-cols-1 gap-8 md:grid-cols-2">
            <!-- Booking Information -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Booking Information</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Requested By</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ $booking->user?->name ?? 'N/A' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Vehicle Details</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">
                            @if($booking->vehicle)
                                {{ $booking->vehicle->license_plate }} - {{ $booking->vehicle->make }} {{ $booking->vehicle->model }}
                                ({{ $booking->vehicle->year }})
                            @else
                                No vehicle assigned
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Assigned Driver</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">
                            @if($booking->driver)
                                {{ $booking->driver->name }}
                                @if($booking->driver->phone)
                                    <span class="text-gray-500 dark:text-gray-400 block">Phone: {{ $booking->driver->phone }}</span>
                                @endif
                            @else
                                No driver assigned
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <!-- Trip Details -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Trip Details</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Trip Duration</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">
                            {{ $booking->start_date->format('M d, Y') }} to {{ $booking->end_date->format('M d, Y') }}
                            <span class="text-gray-500 dark:text-gray-400">
                                ({{ $booking->start_date->diffInDays($booking->end_date) + 1 }} days)
                            </span>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Purpose</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $booking->purpose }}</p>
                    </div>
                    
                    @if($booking->notes)
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Additional Notes</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ $booking->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Approval History -->
        @if($booking->approvals->count() > 0)
        <div class="px-6 py-5 border-t border-gray-200 dark:border-zinc-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Approval History</h3>
            <div class="space-y-4">
                @foreach($booking->approvals as $approval)
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <span class="h-10 w-10 rounded-full bg-gray-200 dark:bg-zinc-700 flex items-center justify-center">
                            @if($approval->status === 'approved')
                                <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            @endif
                        </span>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $approval->approver?->name ?? 'Unknown Approver' }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ ucfirst($approval->status) }} on {{ $approval->created_at->format('M d, Y \a\t h:i A') }}
                        </p>
                        @if($approval->notes)
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $approval->notes }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Footer Actions -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-zinc-800 border-t border-gray-200 dark:border-zinc-700 flex justify-between">
            <a 
                href="{{ route('bookings.index') }}" 
                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-zinc-700 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-white bg-white dark:bg-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Back to Bookings
            </a>
        </div>
    </div>
</div>