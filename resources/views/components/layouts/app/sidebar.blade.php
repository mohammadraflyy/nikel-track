<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <h1 class="font-extrabold text-xl uppercase">Nikel Track</h1>
            </a>

            <flux:navlist variant="outline">
                <!-- Dashboard -->
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item 
                        icon="home" 
                        :href="route('dashboard')" 
                        :current="request()->routeIs('dashboard')" 
                        wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:navlist.item>
                </flux:navlist.group>

                <!-- Bookings -->
                @can('view-bookings')
                <flux:navlist.group :heading="__('Booking Management')" class="grid">
                    @can('view-bookings')
                    <flux:navlist.item 
                        icon="document-text" 
                        :href="route('bookings.index')" 
                        :current="request()->routeIs('bookings.index')" 
                        wire:navigate>
                        {{ __('Bookings') }}
                    </flux:navlist.item>
                    @endcan
                    
                    @can('create-bookings')
                    <flux:navlist.item 
                        icon="plus-circle" 
                        :href="route('bookings.create')" 
                        :current="request()->routeIs('bookings.create')" 
                        wire:navigate>
                        {{ __('New Booking') }}
                    </flux:navlist.item>
                    @endcan
                </flux:navlist.group>
                @endcan

                <!-- Approvals -->
                @can('approve-bookings')
                <flux:navlist.group :heading="__('Approval System')" class="grid">
                    <flux:navlist.item 
                        icon="check-circle" 
                        :href="route('approvals.pending')" 
                        :current="request()->routeIs('approvals.*')" 
                        wire:navigate>
                        {{ __('Pending Approvals') }}
                        @if(auth()->user()->approvals()->where('status', 'pending')->count() > 0)
                        <flux:badge color="red" rounded="full" size="sm">
                            {{ auth()->user()->approvals()->where('status', 'pending')->count() }}
                        </flux:badge>
                        @endif
                    </flux:navlist.item>
                </flux:navlist.group>
                @endcan

                <!-- Vehicle Management -->
                @can('manage-vehicles')
                <flux:navlist.group :heading="__('Vehicle Control')" class="grid">
                    <flux:navlist.item 
                        icon="truck" 
                        :href="route('vehicles.index')" 
                        :current="request()->routeIs('vehicles.index')" 
                        wire:navigate>
                        {{ __('Vehicles') }}
                    </flux:navlist.item>
                    
                    <!-- Vehicle Maintenance Section -->
                    <flux:navlist.group :heading="__('Maintenance')" class="grid">
                        <flux:navlist.item 
                            icon="fire" 
                            :href="route('fuel-logs.index')" 
                            :current="request()->routeIs('fuel-logs.*')" 
                            wire:navigate>
                            {{ __('Fuel Logs') }}
                        </flux:navlist.item>
                        
                        <flux:navlist.item 
                            icon="wrench-screwdriver" 
                            :href="route('service-logs.index')" 
                            :current="request()->routeIs('service-logs.*')" 
                            wire:navigate>
                            {{ __('Service Logs') }}
                        </flux:navlist.item>
                        
                        <flux:navlist.item 
                            icon="clock" 
                            :href="route('usage-logs.index')" 
                            :current="request()->routeIs('usage-logs.*')" 
                            wire:navigate>
                            {{ __('Usage Logs') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                </flux:navlist.group>
                @endcan

                <!-- Reports -->
                @can('view-reports')
                <flux:navlist.group :heading="__('Reports')" class="grid">
                    <flux:navlist.item 
                        icon="chart-bar" 
                        :href="route('reports.bookings')" 
                        :current="request()->routeIs('reports.*')" 
                        wire:navigate>
                        {{ __('Booking Reports') }}
                    </flux:navlist.item>
                    
                    <!-- Maintenance Reports -->
                    <flux:navlist.group :heading="__('Maintenance Reports')" class="grid">
                        <flux:navlist.item 
                            icon="chart-pie" 
                            :href="route('reports.fuel')" 
                            :current="request()->routeIs('reports.fuel')" 
                            wire:navigate>
                            {{ __('Fuel Consumption') }}
                        </flux:navlist.item>
                        
                        <flux:navlist.item 
                            icon="chart-bar-square" 
                            :href="route('reports.maintenance')" 
                            :current="request()->routeIs('reports.maintenance')" 
                            wire:navigate>
                            {{ __('Maintenance Costs') }}
                        </flux:navlist.item>

                        <flux:navlist.item 
                            icon="calendar-days" 
                            :href="route('reports.usage')" 
                            :current="request()->routeIs('reports.usage')" 
                            wire:navigate>
                            {{ __('History Usage') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                </flux:navlist.group>
                @endcan

                <!-- Admin Sections -->
                @canany(['manage-users', 'manage-drivers', 'view-system-logs'])
                <flux:navlist.group :heading="__('Administration')" class="grid">
                    @can('manage-users')
                    <flux:navlist.item 
                        icon="users" 
                        :href="route('users.index')" 
                        :current="request()->routeIs('users.*')" 
                        wire:navigate>
                        {{ __('User Management') }}
                    </flux:navlist.item>
                    @endcan
                    
                    @can('manage-drivers')
                    <flux:navlist.item 
                        icon="identification" 
                        :href="route('drivers.index')" 
                        :current="request()->routeIs('drivers.*')" 
                        wire:navigate>
                        {{ __('Driver Management') }}
                    </flux:navlist.item>
                    @endcan
                    
                    @can('view-system-logs')
                    <flux:navlist.item 
                        icon="document-chart-bar" 
                        :href="route('logs.system')" 
                        :current="request()->routeIs('logs.system')" 
                        wire:navigate>
                        {{ __('System Logs') }}
                    </flux:navlist.item>
                    @endcan
                </flux:navlist.group>
                @endcanany
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <x-notification />
        
        @fluxScripts
    </body>
</html>