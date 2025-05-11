<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@nikeltrack.com',
            'password' => Hash::make('Admin@1234'),
        ]);
        $admin->assignRole('admin');

        // Create approver level 1 user
        $approver1 = User::create([
            'name' => 'Approver Level 1',
            'email' => 'approver1@nikeltrack.com',
            'password' => Hash::make('Approver1@1234'),
        ]);
        $approver1->assignRole('approver_level1');

        // Create approver level 2 user
        $approver2 = User::create([
            'name' => 'Approver Level 2',
            'email' => 'approver2@nikeltrack.com',
            'password' => Hash::make('Approver2@1234'),
        ]);
        $approver2->assignRole('approver_level2');
    }
}
