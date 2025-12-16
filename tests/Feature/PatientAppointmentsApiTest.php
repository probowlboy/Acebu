<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientAppointmentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_fetch_their_appointments_and_response_contains_patient_info()
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $dentist = User::factory()->create(['role' => 'admin']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'service_name' => $service->name,
            'description' => 'Test appointment',
            'appointment_date' => now()->addDays(2),
            'status' => 'pending',
            'notes' => ''
        ]);

        $this->actingAs($patient, 'sanctum');

        // Also create another patient's appointment to ensure it doesn't leak to this patient
        $otherPatient = User::factory()->create(['role' => 'patient']);
        Appointment::create([
            'patient_id' => $otherPatient->id,
            'dentist_id' => $dentist->id,
            'service_name' => $service->name,
            'description' => 'Other appointment',
            'appointment_date' => now()->addDays(3),
            'status' => 'pending',
            'notes' => ''
        ]);

        $response = $this->getJson('/api/patient/appointments');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $first = $data[0];
        $this->assertArrayHasKey('patient_id', $first);
        $this->assertEquals($patient->id, $first['patient_id']);
        $this->assertArrayHasKey('patient', $first);
        $this->assertEquals($patient->email, $first['patient']['email']);
        $this->assertEquals($appointment->id, $first['id']);
        // Ensure other patient's appointment did not appear
        $patientIds = array_map(fn($a) => $a['patient_id'], $data);
        $this->assertNotContains($otherPatient->id, $patientIds); // other patient's id should not be present in API response
        $this->assertContains($patient->id, $patientIds); // current patient's id should be present
        $this->assertCount(1, $data); // only one appointment should be returned
    }

    public function test_admin_cannot_access_patient_appointments_endpoint()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson('/api/patient/appointments');
        $response->assertStatus(403);
    }

    public function test_patient_response_contains_pending_and_confirmed_appointments()
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $dentist = User::factory()->create(['role' => 'admin']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $pending = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'service_name' => $service->name,
            'description' => 'Pending appointment',
            'appointment_date' => now()->startOfDay()->addHours(10),
            'status' => 'pending',
            'notes' => ''
        ]);

        $confirmed = Appointment::create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'service_name' => $service->name,
            'description' => 'Confirmed appointment',
            'appointment_date' => now()->startOfDay()->addHours(12),
            'status' => 'confirmed',
            'notes' => ''
        ]);

        $this->actingAs($patient, 'sanctum');

        $response = $this->getJson('/api/patient/appointments');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $statuses = array_map(fn($a) => $a['status'], $data);
        $this->assertContains('pending', $statuses);
        $this->assertContains('confirmed', $statuses);
        $this->assertEquals(1, array_count_values($statuses)['pending'] ?? 0);
        $this->assertEquals(1, array_count_values($statuses)['confirmed'] ?? 0);
    }

    public function test_patient_cancel_creates_single_notifications()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['role' => 'patient']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_name' => $service->name,
            'description' => 'To be cancelled',
            'appointment_date' => now(),
            'status' => 'pending',
            'notes' => ''
        ]);

        $this->actingAs($patient, 'sanctum');
        $response = $this->postJson("/api/patient/appointments/{$appointment->id}/cancel");
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);

        $this->assertEquals(1, \App\Models\Notification::where('user_id', $patient->id)->where('title', 'Appointment cancelled')->count());
        $this->assertEquals(1, \App\Models\Notification::where('title', 'Appointment cancelled')->where('user_id', $admin->id)->count());
    }
}
