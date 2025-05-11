<?php

namespace Database\Seeders;

use App\Models\Driver;
use Illuminate\Database\Seeder;

class DriversTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $drivers = [
            [
                'name' => 'John Doe',
                'license_number' => 'DL12345678',
                'status' => 'available',
            ],
            [
                'name' => 'Jane Smith',
                'license_number' => 'DL87654321',
                'status' => 'available',
            ],
            [
                'name' => 'Robert Johnson',
                'license_number' => 'DL56781234',
                'status' => 'available',
            ],
            [
                'name' => 'Emily Davis',
                'license_number' => 'DL43218765',
                'status' => 'available',
            ],
            [
                'name' => 'Michael Wilson',
                'license_number' => 'DL98765432',
                'status' => 'available',
            ],
        ];

        foreach ($drivers as $driver) {
            Driver::create($driver);
        }
    }
}