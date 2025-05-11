<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\SystemLog;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $logToDelete = null;
    public $showDeleteModal = false;
    public $selectedAction = 'all';
    public $selectedTable = 'all';
    public $detailLog = null;
    public $showDetailModal = false;

    public function logs()
    {
        return SystemLog::with('user')
            ->when($this->search, function($q) {
                $q->where('description', 'like', "%{$this->search}%")
                  ->orWhere('action', 'like', "%{$this->search}%");
            })
            ->when($this->selectedAction !== 'all', fn($q) => $q->where('action', $this->selectedAction))
            ->when($this->selectedTable !== 'all', fn($q) => $q->where('table_name', $this->selectedTable))
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function clearAllLogs()
    {
        SystemLog::truncate();
        session()->flash('message', 'All logs cleared successfully!');
    }

    public function showDetail($logId)
    {
        $this->detailLog = SystemLog::with('user')->find($logId);
        $this->showDetailModal = true;
    }

    public function getTableOptionsProperty()
    {
        return SystemLog::distinct('table_name')->pluck('table_name');
    }

    public function getActionOptionsProperty()
    {
        return SystemLog::distinct('action')->pluck('action');
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'System Logs', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">System Logs</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track and manage system activities</p>
            </div>
            <div class="flex space-x-2">
                <button 
                    wire:click="clearAllLogs" 
                    wire:confirm="Are you sure you want to clear all logs? This cannot be undone."
                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Clear All
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 shadow rounded-lg">
        <div class="p-6">
            <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
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
                        placeholder="Search logs...">
                </div>
                
                <div>
                    <select 
                        wire:model.live="selectedAction" 
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 dark:bg-zinc-800 dark:text-white">
                        <option value="all">All Actions</option>
                        @foreach($this->actionOptions as $action)
                            <option value="{{ $action }}">{{ ucfirst($action) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div>
                    <select 
                        wire:model.live="selectedTable" 
                        class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-3 dark:bg-zinc-800 dark:text-white">
                        <option value="all">All Tables</option>
                        @foreach($this->tableOptions as $table)
                            <option value="{{ $table }}">{{ ucfirst(str_replace('_', ' ', $table)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Action</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Table</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Record ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($this->logs() as $log)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        {{ $log->action === 'delete' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100' : 
                                        ($log->action === 'create' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' :
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100') }}">
                                        {{ ucfirst($log->action) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $log->user?->name ?? 'System' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ str_replace('_', ' ', $log->table_name) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                {{ $log->record_id ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $log->record_id ?? 'N/A' }}
                                        @if(!$log->record_id)
                                            <svg class="ml-1 h-3 w-3 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    {{ Str::limit($log->description, 50) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button 
                                        wire:click="showDetail({{ $log->id }})"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                        View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center p-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No logs found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">System activity will appear here</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->logs()->links() }}
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <x-modal wire:model="showDetailModal" maxWidth="2xl">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white">Log Details</h2>
            </div>
            
            @if($detailLog)
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Date</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $detailLog->created_at->format('Y-m-d H:i:s') }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Action</p>
                            <p class="mt-1 text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $detailLog->action === 'delete' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100' : 
                                    ($detailLog->action === 'create' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100' :
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100') }}">
                                    {{ ucfirst($detailLog->action) }}
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">User</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $detailLog->user?->name ?? 'System' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Table</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ str_replace('_', ' ', $detailLog->table_name) }}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Record ID</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $detailLog->record_id ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address</p>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $detailLog->ip_address ?? 'N/A' }}</p>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</p>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $detailLog->description }}</p>
                    </div>
                    
                    @if($detailLog->old_data || $detailLog->new_data)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if($detailLog->old_data)
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Old Data</p>
                                    <pre class="mt-1 p-2 bg-gray-50 dark:bg-zinc-800 rounded text-xs text-gray-900 dark:text-gray-200 overflow-auto max-h-40">{{ json_encode(json_decode($detailLog->old_data), JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                            @if($detailLog->new_data)
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">New Data</p>
                                    <pre class="mt-1 p-2 bg-gray-50 dark:bg-zinc-800 rounded text-xs text-gray-900 dark:text-gray-200 overflow-auto max-h-40">{{ json_encode(json_decode($detailLog->new_data), JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-modal>

    @if (session()->has('message'))
        <x-notification message="{{ session('message') }}" type="success" />
    @endif
</div>