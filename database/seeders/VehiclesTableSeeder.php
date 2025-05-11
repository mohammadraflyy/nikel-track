<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehiclesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $vehicles = [
            [
                'license_plate' => 'B 1234 AB',
                'type' => 'orang',
                'fuel_consumption' => 0.12,
                'status' => 'available',
            ],
            [
                'license_plate' => 'B 5678 CD',
                'type' => 'orang',
                'fuel_consumption' => 0.15,
                'status' => 'available',
            ],
            [
                'license_plate' => 'B 9012 EF',
                'type' => 'barang',
                'fuel_consumption' => 0.20,
                'status' => 'available',
            ],
            [
                'license_plate' => 'B 3456 GH',
                'type' => 'barang',
                'fuel_consumption' => 0.25,
                'status' => 'available',
            ],
            [
                'license_plate' => 'B 7890 IJ',
                'type' => 'orang',
                'fuel_consumption' => 0.10,
                'status' => 'available',
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}