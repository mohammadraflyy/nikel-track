<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // Bookings
    Volt::route('bookings', 'bookings.index')
        ->name('bookings.index')
        ->middleware('can:view-bookings');
        
    Volt::route('bookings/create', 'bookings.create')
        ->name('bookings.create')
        ->middleware('can:create-bookings');
        
    Volt::route('bookings/show/{id}', 'bookings.show')
        ->name('bookings.show')
        ->middleware('can:view-booking');

    // Approvals
    Volt::route('approvals/pending', 'approvals.pending')
        ->name('approvals.pending')
        ->middleware('can:approve-bookings');

    // Vehicles (admin only)
    Volt::route('vehicles', 'vehicles.index')
        ->name('vehicles.index')
        ->middleware('can:manage-vehicles');
        
    Volt::route('vehicles/show', 'vehicles.show')
        ->name('vehicles.show')
        ->middleware('can:manage-vehicles');
        
    Volt::route('vehicles/create', 'vehicles.create')
        ->name('vehicles.create')
        ->middleware('can:manage-vehicles');
        
    Volt::route('vehicles/edit/{id}', 'vehicles.edit')
        ->name('vehicles.edit')
        ->middleware('can:manage-vehicles');

    // Reports
    Volt::route('reports/bookings', 'reports.bookings')
        ->name('reports.bookings')
        ->middleware('can:view-reports');
    Volt::route('reports/fuel', 'reports.fuel')
        ->name('reports.fuel')
        ->middleware('can:view-reports');
    Volt::route('reports/maintenance', 'reports.maintenance')
        ->name('reports.maintenance')
        ->middleware('can:view-reports');
    Volt::route('reports/usage', 'reports.usage')
        ->name('reports.usage')
        ->middleware('can:view-reports');

    // fuel-logs
    Volt::route('fuel-logs', 'fuel-logs.index')
        ->name('fuel-logs.index')
        ->middleware('can:manage-fuel-logs');
        
    Volt::route('fuel-logs/create', 'fuel-logs.create')
        ->name('fuel-logs.create')
        ->middleware('can:manage-fuel-logs');
        
    Volt::route('fuel-logs/edit/{id}', 'fuel-logs.edit')
        ->name('fuel-logs.edit')
        ->middleware('can:manage-fuel-logs');

    // service-logs
    Volt::route('service-logs', 'service-logs.index')
        ->name('service-logs.index')
        ->middleware('can:manage-service-logs');
        
    Volt::route('service-logs/create', 'service-logs.create')
        ->name('service-logs.create')
        ->middleware('can:manage-service-logs');
        
    Volt::route('service-logs/edit/{id}', 'service-logs.edit')
        ->name('service-logs.edit')
        ->middleware('can:manage-service-logs');
    
    // usage-logs
    Volt::route('usage-logs', 'usage-logs.index')
        ->name('usage-logs.index')
        ->middleware('can:manage-usage-logs');
        
    Volt::route('usage-logs/create', 'usage-logs.create')
        ->name('usage-logs.create')
        ->middleware('can:manage-usage-logs');
        
    Volt::route('usage-logs/edit/{id}', 'usage-logs.edit')
        ->name('usage-logs.edit')
        ->middleware('can:manage-usage-logs');

    // Users (admin only)
    Volt::route('users', 'users.index')
        ->name('users.index')
        ->middleware('can:manage-users');
        
    Volt::route('users/create', 'users.create')
        ->name('users.create')
        ->middleware('can:manage-users');
        
    Volt::route('users/edit/{id}', 'users.edit')
        ->name('users.edit')
        ->middleware('can:manage-users');

    // Drivers (admin only)
    Volt::route('drivers', 'drivers.index')
        ->name('drivers.index')
        ->middleware('can:manage-drivers');
        
    Volt::route('drivers/create', 'drivers.create')
        ->name('drivers.create')
        ->middleware('can:manage-drivers');
        
    Volt::route('drivers/edit/{id}', 'drivers.edit')
        ->name('drivers.edit')
        ->middleware('can:manage-drivers');
    
    // Syslog 
    Volt::route('logs/system', 'logs.system')
        ->name('logs.system')
        ->middleware('can:view-system-logs');

    // Settings (all authenticated users)
    Volt::route('settings/profile', 'settings.profile')
        ->name('settings.profile')
        ->middleware('can:update-profile');
        
    Volt::route('settings/password', 'settings.password')
        ->name('settings.password')
        ->middleware('can:update-password');
        
    Volt::route('settings/appearance', 'settings.appearance')
        ->name('settings.appearance');
});

require __DIR__.'/auth.php';