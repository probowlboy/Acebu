<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockedSlotsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_cancel_marks_slot_as_blocked()
    {
        // Create an admin and a patient
        $admin = User::factory()->create(['role' => 'admin']);
        $patient = User::factory()->create(['role' => 'patient']);

        // Create an appointment in the future
        $dt = now()->addDays(1)->setHour(9)->setMinute(0)->setSecond(0);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'service_name' => 'Test Service',
            'appointment_date' => $dt,
            'status' => 'confirmed',
        ]);

        // Admin calls cancel
        $this->actingAs($admin)->postJson("/api/admin/appointments/{$appointment->id}/cancel")->assertStatus(200);

        $dateKey = $dt->format('Y-m-d');
        $timeKey = $dt->format('H:i');

        // Call blocked slots endpoint
        $resp = $this->getJson("/api/appointments/{$dateKey}/blocked");
        $resp->assertStatus(200)->assertJsonFragment(['blocked_slots' => [$timeKey]]);
    }
}
