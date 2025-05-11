<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\SystemLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public User $user;
    public string $name = '';
    public string $email = '';
    public string $role = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $previousRole = '';

    public function mount($id)
    {
        try {
            $this->user = User::with('roles')->findOrFail($id);
            $this->name = $this->user->name;
            $this->email = $this->user->email;
            $this->role = $this->user->roles->first()->name ?? '';
            $this->previousRole = $this->role;

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'view_edit',
                'table_name' => 'users',
                'record_id' => $this->user->id,
                'description' => 'Viewing edit form for user: ' . $this->user->name . 
                               ' (ID: ' . $this->user->id . ') | Current role: ' . $this->role
            ]);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'error',
                'table_name' => 'users',
                'record_id' => $id,
                'description' => 'Failed to load user for editing: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to load user: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$this->user->id],
            'role' => ['required', 'string', 'exists:roles,name'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function save()
    {
        try {
            $validated = $this->validate();

            // Log before update
            $changes = [
                'name' => $this->name !== $this->user->name ? [$this->user->name, $validated['name']] : null,
                'email' => $this->email !== $this->user->email ? [$this->user->email, $validated['email']] : null,
                'role' => $this->role !== $this->previousRole ? [$this->previousRole, $validated['role']] : null,
                'password' => !empty($validated['password']) ? ['******', '******'] : null
            ];

            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_attempt',
                'table_name' => 'users',
                'record_id' => $this->user->id,
                'description' => 'Attempting to update user: ' . $this->user->name . 
                               ' (ID: ' . $this->user->id . ') | Changes: ' . json_encode(array_filter($changes))
            ]);

            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $this->user->update($updateData);

            // Sync roles if changed
            if ($this->role !== $this->previousRole) {
                $this->user->syncRoles([$validated['role']]);
            }

            // Log successful update
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_success',
                'table_name' => 'users',
                'record_id' => $this->user->id,
                'description' => 'Successfully updated user: ' . $this->user->name . 
                               ' (ID: ' . $this->user->id . ') | Changes applied: ' . json_encode(array_filter($changes))
            ]);

            session()->flash('message', 'User updated successfully!');
            $this->redirect(route('users.index'), navigate: true);

        } catch (\Exception $e) {
            SystemLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_failed',
                'table_name' => 'users',
                'record_id' => $this->user->id,
                'description' => 'Failed to update user: ' . $this->user->name . 
                               ' (ID: ' . $this->user->id . ') | Error: ' . $e->getMessage()
            ]);

            $this->dispatch('notify', [
                'message' => 'Failed to update user: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <div class="mb-8">
        <x-breadcrumbs :links="[
            ['text' => 'Dashboard', 'url' => route('dashboard')],
            ['text' => 'Users', 'url' => route('users.index')],
            ['text' => 'Edit User', 'url' => '#'],
        ]" />
        
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit User</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update user details</p>
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
                    
                    <!-- Email -->
                    <div class="sm:col-span-2">
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Email Address</label>
                        <input 
                            wire:model="email" 
                            type="email" 
                            id="email" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white"
                            required>
                        @error('email') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Role -->
                    <div class="sm:col-span-2">
                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Role</label>
                        <select 
                            wire:model="role" 
                            id="role" 
                            class="block w-full rounded-md border border-gray-300 dark:border-zinc-700 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                            @foreach(Role::all() as $roleItem)
                                <option value="{{ $roleItem->name }}" class="bg-white dark:bg-zinc-800" {{ $this->role === $roleItem->name ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $roleItem->name)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">New Password (optional)</label>
                        <input 
                            wire:model="password" 
                            type="password" 
                            id="password" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                        @error('password') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    
                    <!-- Password Confirmation -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Confirm New Password</label>
                        <input 
                            wire:model="password_confirmation" 
                            type="password" 
                            id="password_confirmation" 
                            class="block w-full py-2 px-3 rounded-md border border-gray-300 dark:border-zinc-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-zinc-800 dark:text-white">
                    </div>
                </div>
                
                <div class="flex justify-end mt-6 space-x-4">
                    <a 
                        href="{{ route('users.index') }}" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-zinc-700 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-white bg-white dark:bg-zinc-800 hover:bg-gray-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        wire:navigate>
                        Cancel
                    </a>
                    
                    <button 
                        type="submit" 
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg wire:loading.remove wire:target="save" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>