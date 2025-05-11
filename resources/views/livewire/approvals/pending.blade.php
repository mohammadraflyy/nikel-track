<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Approval;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use WithPagination;

    public string $selectedLevel = 'all';
    public string $search = '';
    public array $notes = [];

    public function approvals()
    {
        return auth()->user()
            ->approvals()
            ->with(['booking.vehicle', 'booking.driver', 'booking.user', 'booking.approvals'])
            ->when($this->search, fn($q) => $q->whereHas('booking', fn($q) => 
                $q->where('purpose', 'like', "%{$this->search}%")
            ))
            ->where('status', 'pending')
            ->latest()
            ->paginate(8);
    }

    public function approve($approvalId)
    {
        if (empty($approvalId)) {
            Log::error('Empty approvalId in approve()');
            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'approve',
                'table_name' => 'approvals',
                'record_id' => null,
                'description' => 'Attempted approval with empty approval ID'
            ]);
            return;
        }

        try {
            $approval = Approval::findOrFail($approvalId);
            $user = auth()->user();
            $booking = $approval->booking;

            if (!$user->hasRole('approver_level'.$approval->level)) {
                $this->dispatch('notify', 
                    message: 'You are not authorized to approve at this level',
                    type: 'error'
                );
                
                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'unauthorized_approval_attempt',
                    'table_name' => 'approvals',
                    'record_id' => $approvalId,
                    'description' => 'User attempted to approve without proper permissions for level '.$approval->level
                ]);
                return;
            }

            if ($approval->level === '1') {
                DB::transaction(function () use ($approval, $booking) {
                    $approval->update([
                        'status' => 'approved',
                        'updated_at' => now(),
                    ]);
                    $booking->update(['status' => 'approved_1']);

                    SystemLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'approve',
                        'table_name' => 'approvals',
                        'record_id' => $approval->id,
                        'description' => 'Approved booking (Level 1). Booking ID: '.$booking->id
                    ]);
                });

            } elseif ($approval->level === '2') {
                $level1Approved = $booking->approvals()
                    ->where('level', '1')
                    ->where('status', 'approved')
                    ->exists();
                
                if (!$level1Approved) {
                    $this->dispatch('notify',
                        message: 'Level 1 approval must be completed first',
                        type: 'error'
                    );

                    SystemLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'approval_attempt',
                        'table_name' => 'approvals',
                        'record_id' => $approval->id,
                        'description' => 'Attempted Level 2 approval without Level 1 approval for booking '.$booking->id
                    ]);
                    return;
                }

                DB::transaction(function () use ($approval, $booking) {
                    $approval->update([
                        'status' => 'approved',
                        'updated_at' => now(),
                    ]);

                    $booking->update(['status' => 'approved']);

                    SystemLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'approve',
                        'table_name' => 'approvals',
                        'record_id' => $approval->id,
                        'description' => 'Approved booking (Level 2). Booking ID: '.$booking->id
                    ]);
                });
            }

            $this->dispatch('notify',
                message: 'Approval processed successfully!',
                type: 'success'
            );
            
        } catch (\Exception $e) {
            $this->dispatch('notify',
                message: 'An error occurred while processing approval: '. $e->getMessage(),
                type: 'error'
            );
            
            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'approval_error',
                'table_name' => 'approvals',
                'record_id' => $approvalId ?? null,
                'description' => 'Approval failed: '.$e->getMessage()
            ]);
            
            Log::error('Approval failed: '.$e->getMessage());
        }

        $this->resetPage();
    }

    public function reject($approvalId)
    {
        try {
            $approval = Approval::findOrFail($approvalId);
            $user = auth()->user();
            $booking = $approval->booking;

            if (!$user->hasRole('approver_level'.$approval->level)) {
                $this->dispatch('notify', 
                    message: 'You are not authorized to approve at this level',
                    type: 'error'
                );

                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'unauthorized_rejection_attempt',
                    'table_name' => 'approvals',
                    'record_id' => $approvalId,
                    'description' => 'User attempted to reject without proper permissions for level '.$approval->level
                ]);
                return;
            }

            if ($approval->level == 2) {
                $level1Approval = $booking->approvals()->where('level', 1)->first();

                if (!$level1Approval || $level1Approval->status === 'pending') {
                    $this->dispatch('notify', 
                        message: 'Level 1 must approve before Level 2 can reject',
                        type: 'error'
                    );

                    SystemLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'rejection_attempt',
                        'table_name' => 'approvals',
                        'record_id' => $approval->id,
                        'description' => 'Attempted Level 2 rejection without Level 1 approval for booking '.$booking->id
                    ]);
                    return;
                }

                if ($level1Approval->status === 'rejected') {
                    $this->dispatch('notify', 
                        message: 'Cannot reject - Level 1 has already rejected this booking',
                        type: 'error'
                    );

                    SystemLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'rejection_attempt',
                        'table_name' => 'approvals',
                        'record_id' => $approval->id,
                        'description' => 'Attempted Level 2 rejection when Level 1 already rejected booking '.$booking->id
                    ]);
                    return;
                }
            }

            DB::transaction(function () use ($approval, $booking) {
                $approval->update([
                    'status' => 'rejected',
                    'updated_at' => now(),
                ]);

                $booking->update(['status' => 'rejected']);

                SystemLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'reject',
                    'table_name' => 'approvals',
                    'record_id' => $approval->id,
                    'description' => 'Rejected booking (Level '.$approval->level.'). Booking ID: '.$booking->id
                ]);
            });

            $this->dispatch('notify', 
                message: 'Approval rejected successfully!',
                type: 'success'
            );
        } catch (\Exception $e) {
            $this->dispatch('notify', 
                message: 'An error occurred while processing rejection: '.$e->getMessage(),
                type: 'error'
            );

            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => 'rejection_error',
                'table_name' => 'approvals',
                'record_id' => $approvalId ?? null,
                'description' => 'Rejection failed: '.$e->getMessage()
            ]);

            Log::error('Rejection failed: '.$e->getMessage());
        }

        $this->resetPage();
    }
}; ?>
<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Bookings', 'url' => route('bookings.index')],
            ['text' => 'Approvals', 'url' => '#'],
        ]" />

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pending Approvals</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    You have {{ auth()->user()->approvals()->where('status', 'pending')->count() }} pending requests
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2">
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
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-10 focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white" 
                        placeholder="Search bookings...">
                </div>
                
                <!-- Stats -->
                <div class="flex items-center justify-end space-x-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        Total: {{ $this->approvals()->total() }}
                    </span>
                </div>
            </div>

            <div class="space-y-4">
                @forelse($this->approvals() as $approval)
                    <div class="bg-white dark:bg-zinc-800 shadow rounded-lg overflow-hidden" wire:key="approval-{{ $approval->id }}">
                        <div class="grid grid-cols-1 md:grid-cols-3">
                            <!-- Booking Info -->
                            <div class="p-4 border-b md:border-b-0 md:border-r border-gray-200 dark:border-zinc-700">
                                <div class="flex items-center space-x-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $approval->level === 1 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100' : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100' }}">
                                        Level {{ $approval->level }}
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $approval->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                
                                <h3 class="mt-2 font-medium text-gray-900 dark:text-white">
                                    {{ $approval->booking->purpose }}
                                </h3>
                                
                                <div class="mt-3 space-y-1 text-sm">
                                    <div class="flex items-center text-gray-600 dark:text-gray-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        {{ $approval->booking->vehicle->license_plate }}
                                    </div>
                                    <div class="flex items-center text-gray-600 dark:text-gray-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        {{ $approval->booking->driver->name }}
                                    </div>
                                    <div class="flex items-center text-gray-600 dark:text-gray-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {{ $approval->booking->start_date->format('M d') }} - 
                                        {{ $approval->booking->end_date->format('M d, Y') }}
                                    </div>
                                </div>

                                <!-- Approval status indicator -->
                                <div class="mt-3">
                                    @if($approval->level === 2 && $approval->booking->status === 'approved_1')
                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2h-1V9z" clip-rule="evenodd" />
                                            </svg>
                                            Ready for your approval
                                        </span>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Requester Info -->
                            <div class="p-4 border-b md:border-b-0 md:border-r border-gray-200 dark:border-zinc-700">
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Requested By</h4>
                                <div class="flex items-center mt-2 space-x-3">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-300 dark:bg-zinc-600 flex items-center justify-center text-gray-600 dark:text-gray-300 font-medium">
                                        {{ substr($approval->booking->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $approval->booking->user->name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $approval->booking->created_at->format('M d, Y h:i A') }}
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="mt-4 space-y-2">
                                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Approval Progress</h4>
                                    
                                    <!-- Level 1 Status -->
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Level 1</span>
                                        @php $level1Approval = $approval->booking->approvals->where('level', 1)->first() @endphp
                                        @if($level1Approval)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $level1Approval->status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' : ($level1Approval->status === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100') }}">
                                                {{ ucfirst($level1Approval->status) }}
                                                @if($level1Approval->status === 'approved')
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                Not required
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <!-- Level 2 Status -->
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Level 2</span>
                                        @php $level2Approval = $approval->booking->approvals->where('level', 2)->first() @endphp
                                        @if($level2Approval)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $level2Approval->status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' : ($level2Approval->status === 'pending' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100') }}">
                                                {{ ucfirst($level2Approval->status) }}
                                                @if($level2Approval->status === 'approved')
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                Waiting for approval level 1
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="p-4">
                                <div class="flex flex-col h-full space-y-3">
                                    @if($approval->status === 'pending')
                                        <div class="flex space-x-3 mt-auto">
                                            <!-- Approve Button -->
                                            <button 
                                                wire:click="approve('{{ $approval->id }}')"
                                                wire:target="approve"
                                                wire:loading.attr="disabled"
                                                @if($approval->level === 2 && !$approval->booking->approvals()->where('level', 1)->where('status', 'approved')->exists()) disabled @endif
                                                class="inline-flex items-center justify-center w-full px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                                <!-- Normal State -->
                                                <span wire:loading.remove wire:target="approve" class="flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Approve
                                                </span>
                                                
                                                <!-- Loading State -->
                                                <span wire:loading wire:target="approve" class="flex items-center">
                                                    <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </span>
                                            </button>

                                            <!-- Reject Button -->
                                            <button 
                                                wire:click="reject('{{ $approval->id }}')"
                                                wire:target="reject"
                                                wire:loading.attr="disabled"
                                                @if($approval->level === 2 && !$approval->booking->approvals()->where('level', 1)->whereIn('status', ['approved', 'rejected'])->exists()) disabled @endif
                                                class="inline-flex items-center justify-center w-full px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                                <span wire:loading.remove wire:target="reject" class="flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Reject
                                                </span>
                                                <span wire:loading wire:target="reject" class="flex items-center">
                                                    <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                        </div>

                                        @if($approval->level === 2 && $approval->booking->status !== 'approved_1')
                                            <div class="mt-2 p-2 bg-yellow-50 dark:bg-yellow-900/30 rounded-md">
                                                <div class="flex items-center text-sm text-yellow-700 dark:text-yellow-300">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                    <span>This requires Level 1 approval before you can approve it.</span>
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <p class="mt-2 text-sm">
                                                @if($approval->status === 'approved')
                                                    You've already approved this request
                                                @elseif($approval->status === 'rejected')
                                                    You've rejected this request
                                                @else
                                                    No action required
                                                @endif
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center bg-white dark:bg-zinc-800 rounded-lg shadow p-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No pending approvals</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You're all caught up! No pending approvals at the moment.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $this->approvals()->links() }}
            </div>
        </div>
    </div>
</div>