<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $adminExists = User::where('username', 'admin')
            ->orWhere('email', 'admin@acebu.com')
            ->first();

        if (!$adminExists) {
            User::create([
                'name' => 'Administrator',
                'email' => 'admin@acebu.com',
                'username' => 'admin',
                'password' => Hash::make('admin'), // Explicitly hash the password
                'role' => 'admin',
                'phone' => '+63 9 1234 5678',
                'country' => 'Philippines',
                'municipality' => 'Cagayan de Oro',
                'province' => 'Misamis Oriental',
                'barangay' => 'Bugo',
                'zip_code' => '9000',
                'zone_street' => 'Zone 3',
                'birthday' => '1990-01-01',
            ]);

            $this->command->info('Admin user created successfully!');
            $this->command->info('Username: admin');
            $this->command->info('Password: admin');
        } else {
            // Reset password to ensure it works
            $adminExists->password = Hash::make('admin');
            $adminExists->save();
            $this->command->info('Admin user already exists! Password reset to: admin');
            $this->command->info('Username: ' . $adminExists->username);
            $this->command->info('Password: admin');
        }
    }
}
