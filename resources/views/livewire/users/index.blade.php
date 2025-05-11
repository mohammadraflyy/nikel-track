<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $users = [];
    public string $search = '';
    public $userToDelete = null;
    public bool $showDeleteModal = false;

    public function users()
    {
        try {
            $query = User::query()
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->latest();

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'page_load',
                'table_name' => 'users',
                'record_id' => null,
                'description' => 'Viewed user management page'
            ]);

            if ($this->search) {
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'search',
                    'table_name' => 'users',
                    'record_id' => null,
                    'description' => 'Searched users with query: ' . $this->search
                ]);
            }

            return $query->paginate(10);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'users',
                'record_id' => null,
                'description' => 'Failed to load users: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to load users: ' . $e->getMessage(),
                'type' => 'error'
            ]);

            return User::query()->paginate(10);
        }
    }

    public function confirmDelete($id)
    {
        try {
            $this->userToDelete = $id;
            $this->showDeleteModal = true;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_initiated',
                'table_name' => 'users',
                'record_id' => $id,
                'description' => 'Initiated deletion of user ID: ' . $id
            ]);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'users',
                'record_id' => $id,
                'description' => 'Failed to initiate user deletion: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to initiate deletion: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    public function deleteUser()
    {
        try {
            if (!$this->userToDelete) {
                throw new \Exception('No user selected for deletion');
            }

            if ($this->userToDelete == Auth::id()) {
                session()->flash('message', 'You cannot delete your own account!');
                $this->showDeleteModal = false;
                
                SystemLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'delete_attempt_self',
                    'table_name' => 'users',
                    'record_id' => $this->userToDelete,
                    'description' => 'Attempted to delete their own account'
                ]);
                
                return;
            }

            $user = User::findOrFail($this->userToDelete);

            // Log before deletion
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_attempt',
                'table_name' => 'users',
                'record_id' => $user->id,
                'description' => 'Attempting to delete user: ' . $user->name . ' (ID: ' . $user->id . ')'
            ]);

            $user->delete();

            // Log successful deletion
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_success',
                'table_name' => 'users',
                'record_id' => $user->id,
                'description' => 'Successfully deleted user: ' . $user->name . ' (ID: ' . $user->id . ')'
            ]);

            $this->showDeleteModal = false;
            $this->userToDelete = null;
            session()->flash('message', 'User deleted successfully!');

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_failed',
                'table_name' => 'users',
                'record_id' => $this->userToDelete,
                'description' => 'Failed to delete user: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to delete user: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'User Management', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">User Management</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage system users</p>
            </div>
            <div>
                <a href="{{ route('users.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add User
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
                        placeholder="Search users...">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-800">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-900 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($this->users() as $user)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $user->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    {{ $user->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @foreach($user->roles as $role)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $role->name === 'admin' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100' : 
                                            ($role->name === 'approver_level1' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100' :
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100') }}">
                                            {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                        </span>
                                    @endforeach
                                    @if($user->roles->isEmpty())
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            No role assigned
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        @can('manage-users')
                                            <a 
                                                href="{{ route('users.edit', $user->id) }}" 
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        @endcan
                                        
                                        @can('manage-users')
                                            <button 
                                                wire:click="confirmDelete({{ $user->id }})" 
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                @if($user->id === auth()->id()) disabled @endif>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center p-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No users found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add a new user to get started</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->users()->links() }}
            </div>
        </div>
    </div>

    <flux:modal wire:model="showDeleteModal">
        <flux:heading>
            Delete user
        </flux:heading>

        <flux:text>
            Are you sure you want to delete this user? This action cannot be undone.
        </flux:text>

        <div class="flex gap-2 mt-4">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" type="button" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="button" wire:click="deleteUser">Delete user</flux:button>
        </div>
    </flux:modal>

    @if (session()->has('message'))
        <x-notification message="{{ session('message') }}" type="success" />
    @endif
</div>