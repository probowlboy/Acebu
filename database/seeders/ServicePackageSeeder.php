<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServicePackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Cleaning Package (Basic)',
                'description' => 'Standard dental cleaning with plaque and tartar removal',
                'price' => 899.00,
                'original_price' => 1200.00,
                'duration_minutes' => 45,
                'clinic_name' => 'Clinic 1',
                'is_active' => true,
            ],
            [
                'name' => 'Whitening Package (Starter)',
                'description' => 'Single-session LED teeth whitening',
                'price' => 2200.00,
                'original_price' => 3000.00,
                'duration_minutes' => 45,
                'clinic_name' => 'Clinic 2',
                'is_active' => true,
            ],
            [
                'name' => 'Braces Package (Downpayment Promo)',
                'description' => 'Metal braces with discounted downpayment',
                'price' => 9999.00,
                'original_price' => 15000.00,
                'duration_minutes' => 60,
                'clinic_name' => 'Clinic 1',
                'is_active' => true,
            ],
            [
                'name' => 'Root Canal Treatment Package',
                'description' => 'Complete root canal treatment with follow-up care',
                'price' => 4500.00,
                'original_price' => 6000.00,
                'duration_minutes' => 90,
                'clinic_name' => 'Clinic 2',
                'is_active' => true,
            ],
            [
                'name' => 'Teeth Extraction Package',
                'description' => 'Simple tooth extraction with post-extraction care',
                'price' => 1200.00,
                'original_price' => 1500.00,
                'duration_minutes' => 30,
                'clinic_name' => 'Clinic 1',
                'is_active' => true,
            ],
            [
                'name' => 'Dental Check-up Package',
                'description' => 'Comprehensive dental examination and consultation',
                'price' => 500.00,
                'original_price' => 800.00,
                'duration_minutes' => 30,
                'clinic_name' => 'Clinic 2',
                'is_active' => true,
            ],
            [
                'name' => 'Teeth Filling Package',
                'description' => 'Composite filling for cavities',
                'price' => 1500.00,
                'original_price' => 2000.00,
                'duration_minutes' => 45,
                'clinic_name' => 'Clinic 1',
                'is_active' => true,
            ],
            [
                'name' => 'Dental Crown Package',
                'description' => 'Porcelain crown installation',
                'price' => 8000.00,
                'original_price' => 10000.00,
                'duration_minutes' => 120,
                'clinic_name' => 'Clinic 2',
                'is_active' => true,
            ],
        ];

        foreach ($packages as $package) {
            Service::create($package);
        }
    }
}

