<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class ApiPatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_register_with_username_as_email()
    {
        $payload = [
            'name' => 'Jane Patient',
            'email' => 'jane.patient@example.com',
            'username' => 'jane.patient@example.com',
            'password' => 'securepassword',
            'birthday' => '1990-01-01',
            'phone' => '+63 912 345 6789',
            'country' => 'Philippines',
            'municipality' => 'Some Town',
            'province' => 'Some Province',
            'barangay' => 'Some Barangay',
            'zip_code' => '1200',
            'zone_street' => 'Zone 1 Street',
            'gender' => 'Female',
        ];

        $response = $this->postJson('/api/patients/register', $payload);

        if ($response->status() !== 201) {
            fwrite(STDERR, "Response: " . $response->getContent() . PHP_EOL);
        }

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'jane.patient@example.com',
            'username' => 'jane.patient@example.com',
            'role' => 'patient',
            'gender' => 'Female',
        ]);
    }
}
