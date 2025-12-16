<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAppointmentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_appointment_completed()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['role' => 'patient']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_name' => $service->name,
            'description' => 'Test appointment',
            'appointment_date' => now(),
            'status' => 'confirmed',
            'notes' => ''
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'completed']);
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);

        $data = $response->json();
        $this->assertEquals('completed', $data['appointment']['status']);

        // Ensure patient was notified about the completion
        $this->assertEquals(1, \App\Models\Notification::where('user_id', $patient->id)->where('title', 'Appointment status updated')->count());
    }

    public function test_admin_can_cancel_appointment()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['role' => 'patient']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_name' => $service->name,
            'description' => 'Test appointment',
            'appointment_date' => now(),
            'status' => 'pending',
            'notes' => ''
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'cancelled']);
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);

        $data = $response->json();
        $this->assertEquals('cancelled', $data['appointment']['status']);

        // Ensure a notification was created for the patient and a single notification for admin
        $this->assertEquals(1, \App\Models\Notification::where('user_id', $patient->id)->where('title', 'Appointment cancelled')->count());
        $notif = \App\Models\Notification::where('user_id', $patient->id)->where('title', 'Appointment cancelled')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Dental Clinic Acebu needs to cancel your appointment', $notif->message);
        $this->assertStringContainsString('please reschedule', $notif->message);
        $this->assertEquals(1, \App\Models\Notification::where('title', 'Appointment cancelled')->where('user_id', $admin->id)->count());
    }

    public function test_admin_cancel_reflects_to_patient_view()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['role' => 'patient']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_name' => $service->name,
            'description' => 'Test appointment',
            'appointment_date' => now(),
            'status' => 'confirmed',
            'notes' => ''
        ]);

        $this->actingAs($admin, 'sanctum');
        $response = $this->putJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'cancelled']);
        $response->assertStatus(200);

        // Now acting as patient, the appointment should show as cancelled
        $this->actingAs($patient, 'sanctum');
        $resp = $this->getJson('/api/patient/appointments');
        $resp->assertStatus(200);
        $data = $resp->json();
        $this->assertIsArray($data);
        $found = collect($data)->firstWhere('id', $appointment->id);
        $this->assertNotNull($found);
        $this->assertEquals('cancelled', $found['status']);
    }

    public function test_admin_cannot_complete_pending_appointment()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['role' => 'patient']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_name' => $service->name,
            'description' => 'Test appointment',
            'appointment_date' => now(),
            'status' => 'pending',
            'notes' => ''
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson("/api/admin/appointments/{$appointment->id}/status", ['status' => 'completed']);
        $response->assertStatus(400);
    }

    public function test_admin_can_cancel_via_admin_cancel_route()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['role' => 'patient']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_name' => $service->name,
            'description' => 'Test appointment',
            'appointment_date' => now(),
            'status' => 'confirmed',
            'notes' => ''
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson("/api/admin/appointments/{$appointment->id}/cancel");
        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
        $data = $response->json();
        $this->assertEquals('cancelled', $data['appointment']['status']);

        // Ensure single notification for patient (and one for admin)
        $this->assertEquals(1, \App\Models\Notification::where('user_id', $patient->id)->where('title', 'Appointment cancelled')->count());
        $notif = \App\Models\Notification::where('user_id', $patient->id)->where('title', 'Appointment cancelled')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Dental Clinic Acebu needs to cancel your appointment', $notif->message);
        $this->assertStringContainsString('please reschedule', $notif->message);
        $this->assertEquals(1, \App\Models\Notification::where('title', 'Appointment cancelled')->where('user_id', $admin->id)->count());
    }

    public function test_admin_can_refresh_appointments()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['role' => 'patient']);

        $service = Service::first() ?? Service::create(['name' => 'Test Service', 'price' => 100]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_name' => $service->name,
            'description' => 'Test appointment',
            'appointment_date' => now(),
            'status' => 'confirmed',
            'notes' => ''
        ]);

        // Prime cache to simulate stale cache
        \Illuminate\Support\Facades\Cache::put('appointments_all', ['stale' => true], 60);

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson("/api/admin/appointments/refresh");
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertIsArray($data);
        // Ensure returned list contains our appointment
        $found = collect($data)->firstWhere('id', $appointment->id);
        $this->assertNotNull($found);

        // Cache should have been repopulated by the refresh call
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('appointments_all'));
    }
}
