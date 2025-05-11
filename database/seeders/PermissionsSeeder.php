<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $roles = ['admin', 'approver_level1', 'approver_level2'];
        foreach ($roles as $role) {
            Role::create(['name' => $role]);
        }

        // Create permissions that match your gates
        $permissions = collect([
            'approve-bookings',
            'view-bookings',
            'create-bookings',
            'view-booking',
            'manage-vehicles',
            'view-reports',
            'manage-users',
            'manage-drivers',
            'update-profile',
            'update-password',
            'view-system-logs',
            'manage-fuel-logs',
            'manage-service-logs',
            'manage-usage-logs',
        ]);

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign all permissions to admin
        $adminRole = Role::findByName('admin');
        $adminRole->givePermissionTo($permissions->except([array_search('approve-bookings', $permissions->toArray())]));
        
        // Assign permissions to approver_level1
        $approver1 = Role::findByName('approver_level1');
        $approver1->givePermissionTo([
            'approve-bookings',
            'update-profile',
            'update-password'
        ]);

        // Assign permissions to approver_level2
        $approver2 = Role::findByName('approver_level2');
        $approver2->givePermissionTo([
            'approve-bookings',
            'update-profile',
            'update-password'
        ]);
    }
}