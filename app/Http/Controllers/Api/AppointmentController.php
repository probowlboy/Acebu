<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * Get patient appointments
     */
    public function getPatientAppointments(Request $request)
    {
        try {
            $user = $request->user();

            // Ensure user is authenticated
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Ensure user is a patient
            if ($user->role !== 'patient') {
                return response()->json([
                    'message' => 'Unauthorized. Only patients can access this endpoint.'
                ], 403);
            }

            $appointments = Appointment::where('patient_id', $user->id)
                ->with(['dentist:id,name', 'patient:id,name,email'])
                ->orderBy('appointment_date', 'desc')
                ->get();

            // Format the response
            // Preload services to avoid N+1 queries
            $serviceNames = $appointments->pluck('service_name')->unique()->values()->toArray();
            $serviceMap = Service::whereIn('name', $serviceNames)->get()->keyBy('name');

            $formattedAppointments = $appointments->map(function ($appointment) use ($serviceMap) {
                // Find service by name from preloaded map
                $service = $serviceMap->get($appointment->service_name);

                // Compute total price if missing: handle multi-service (comma separated) and single service
                $computedTotal = null;
                if ($appointment->total_price || $appointment->total_price === 0) {
                    $computedTotal = $appointment->total_price;
                } else {
                    // try single service
                    if ($service && !str_contains($appointment->service_name, ',')) {
                        $computedTotal = $service->price;
                    } else {
                        // try splitting by comma and summing known services
                        $names = array_map('trim', explode(',', $appointment->service_name ?: ''));
                        $sum = 0;
                        $found = false;
                        foreach ($names as $n) {
                            if ($n === '') continue;
                            $s = $serviceMap->get($n);
                            if ($s && $s->price !== null) {
                                $sum += (float) $s->price;
                                $found = true;
                            }
                        }
                        if ($found) $computedTotal = $sum;
                    }
                }

                // Persist computed total for future requests when available
                $wasTotalNull = $appointment->total_price === null;
                if ($wasTotalNull && $computedTotal !== null) {
                    try {
                        $appointment->total_price = $computedTotal;
                        $appointment->save();
                    } catch (\Exception $e) { /* ignore save failures */ }
                }

                $priceIsEstimated = $wasTotalNull && ($computedTotal !== null);

                return [
                    'id' => $appointment->id,
                    'service_name' => $appointment->service_name,
                    'service' => $service ? [ 'name' => $service->name, 'price' => $service->price ] : null,
                    'price' => $service ? $service->price : null,
                    'total_price' => $computedTotal,
                    'price_is_estimated' => $priceIsEstimated,
                    'description' => $appointment->description,
                    'appointment_date' => $appointment->appointment_date->toISOString(),
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                    'dentist_name' => $appointment->dentist ? $appointment->dentist->name : null,
                    'patient_id' => $appointment->patient_id,
                    'patient' => $appointment->patient ? ['id' => $appointment->patient->id, 'name' => $appointment->patient->name, 'email' => $appointment->patient->email] : null,
                    'created_at' => $appointment->created_at->toISOString(),
                ];
            });

            return response()->json($formattedAppointments, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch appointments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get booked time slots for a specific date (all patients, real-time)
     */
    public function getBookedSlotsForDate(Request $request, $date)
    {
        try {
            // Validate date format (yyyy-mm-dd)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return response()->json([
                    'message' => 'Invalid date format. Use yyyy-mm-dd.'
                ], 400);
            }

            // Fetch all appointments for the given date (excluding cancelled)
            $appointments = Appointment::whereDate('appointment_date', $date)
                ->where('status', '!=', 'cancelled')
                ->get();

            // Extract booked time slots (HH:MM format)
            $bookedSlots = $appointments->map(function ($appointment) {
                return $appointment->appointment_date->format('H:i');
            })->unique()->values();

            return response()->json([
                'date' => $date,
                'booked_slots' => $bookedSlots,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch booked slots',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get blocked slots for a specific date (admin-cancelled slots)
     */
    public function getBlockedSlotsForDate(Request $request, $date)
    {
        try {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return response()->json(['message' => 'Invalid date format. Use yyyy-mm-dd.'], 400);
            }

            $cacheKey = 'blocked_slots_' . $date;
            $blocked = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

            return response()->json([
                'date' => $date,
                'blocked_slots' => array_values(array_unique($blocked)),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch blocked slots',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new appointment
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is a patient
            if ($user->role !== 'patient') {
                return response()->json([
                    'message' => 'Unauthorized. Only patients can create appointments.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'service_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'appointment_date' => 'required|date',
                'notes' => 'nullable|string',
                'total_price' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Ensure appointment_date is a proper future datetime
            $appointmentDate = \Carbon\Carbon::parse($request->appointment_date);
            if ($appointmentDate->lte(now())) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['appointment_date' => ['Appointment date must be in the future.']]
                ], 422);
            }

            // Check if date already has 5 appointments (maximum capacity)
            $appointmentCount = Appointment::whereDate('appointment_date', $appointmentDate->format('Y-m-d'))
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($appointmentCount >= 5) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['appointment_date' => ['This date is fully booked. Please choose another date.']]
                ], 422);
            }

            $appointment = Appointment::create([
                'patient_id' => $user->id,
                'service_name' => $request->service_name,
                'description' => $request->description,
                'appointment_date' => $appointmentDate,
                'status' => 'pending',
                'notes' => $request->notes,
                'total_price' => $request->total_price ?? null,
            ]);

            // Create notification for patient
            Notification::create([
                'user_id' => $user->id,
                'type' => 'appointment',
                'title' => 'Appointment request submitted',
                'message' => 'Your appointment for ' . $appointment->service_name . ' on ' . $appointment->appointment_date->format('M d, Y h:i A') . ' is pending confirmation.',
                'is_read' => false,
                'data' => [
                    'appointment_id' => $appointment->id,
                    'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format(DATE_ATOM) : null,
                ],
            ]);

            // Notify all admins
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'appointment',
                    'title' => 'New appointment request',
                    'message' => $user->name . ' requested an appointment for ' . $appointment->service_name . ' on ' . $appointment->appointment_date->format('M d, Y h:i A') . '.',
                    'is_read' => false,
                    'data' => [
                        'appointment_id' => $appointment->id,
                        'patient_id' => $user->id,
                        'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format(DATE_ATOM) : null,
                    ],
                ]);
            }

            // Include service price in the returned appointment data if available
            $service = Service::where('name', $appointment->service_name)->first();

            // If the client did not provide a total_price, try to compute it and mark as estimated
            $priceIsEstimated = false;
            if ($request->total_price === null) {
                if ($service && $service->price !== null) {
                    if ($appointment->total_price === null) {
                        try { $appointment->total_price = $service->price; $appointment->save(); } catch (\Exception $e) {}
                    }
                    $priceIsEstimated = true;
                }
            }

            // Clear admin cache so newly created appointment is shown promptly
            try { Cache::forget('appointments_all'); } catch (\Exception $e) { }

            return response()->json([
                'message' => 'Appointment created successfully',
                'appointment' => [
                    'id' => $appointment->id,
                    'service_name' => $appointment->service_name,
                    'service' => $service ? ['name' => $service->name, 'price' => $service->price] : null,
                    'price' => $service ? $service->price : null,
                    'total_price' => $appointment->total_price,
                    'price_is_estimated' => $priceIsEstimated,
                    'description' => $appointment->description,
                    'appointment_date' => $appointment->appointment_date ? $appointment->appointment_date->format(DATE_ATOM) : null,
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an appointment
     */
    public function cancel(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            // Ensure user is a patient
            if ($user->role !== 'patient') {
                return response()->json([
                    'message' => 'Unauthorized. Only patients can cancel appointments.'
                ], 403);
            }

            $appointment = Appointment::where('id', $id)
                ->where('patient_id', $user->id)
                ->first();

            if (!$appointment) {
                return response()->json([
                    'message' => 'Appointment not found'
                ], 404);
            }

            if ($appointment->status === 'cancelled') {
                return response()->json([
                    'message' => 'Appointment is already cancelled'
                ], 400);
            }

            if ($appointment->status === 'completed') {
                return response()->json([
                    'message' => 'Cannot cancel a completed appointment'
                ], 400);
            }

            // Use shared cancellation logic; notifications are handled in performCancel
            $this->performCancel($appointment, $user);

            // Clear admin appointments cache so next dashboard poll returns up-to-date list
            try { Cache::forget('appointments_all'); } catch (\Exception $e) { }

            return response()->json([
                'message' => 'Appointment cancelled successfully',
                'appointment' => [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Shared cancel logic used by patient cancel and admin updateStatus
     */
    private function performCancel(Appointment $appointment, ?User $actor = null)
    {
        if ($appointment->status === 'cancelled') {
            return $appointment;
        }

        if ($appointment->status === 'completed') {
            throw new \Exception('Cannot cancel a completed appointment');
        }

        $appointment->status = 'cancelled';
        $appointment->save();

        // Build notification for patient
        try {
            $patientMsg = 'Your appointment for ' . $appointment->service_name . ' on ' . ($appointment->appointment_date ? $appointment->appointment_date->format('M d, Y h:i A') : '') . ' has been cancelled.';

            // If an admin performed the cancellation, send a specific admin-cancel message
            if ($actor && ($actor->role ?? null) === 'admin') {
                $patientName = $appointment->patient->name ?? '';
                $datePart = $appointment->appointment_date ? $appointment->appointment_date->format('l, M d, Y') : '';
                $timePart = $appointment->appointment_date ? $appointment->appointment_date->format('h:i A') : '';
                $patientMsg = 'Hi ' . trim($patientName) . '. Dental Clinic Acebu needs to cancel your appointment ' . trim($datePart) . ' at ' . trim($timePart) . ' due to an unexpected emergency, please reschedule.';
            }

            Notification::create([
                'user_id' => $appointment->patient_id,
                'type' => 'appointment',
                'title' => 'Appointment cancelled',
                'message' => $patientMsg,
                'is_read' => false,
                'data' => [
                    'appointment_id' => $appointment->id,
                    'status' => $appointment->status,
                ],
            ]);
        } catch (\Exception $e) { /* ignore notification failure */ }

        // Notify all admins
        try {
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'appointment',
                    'title' => 'Appointment cancelled',
                    'message' => ($actor && ($actor->name ?? null) ? $actor->name : ($appointment->patient ? $appointment->patient->name : 'A patient')) . ' cancelled an appointment for ' . $appointment->service_name . ' on ' . ($appointment->appointment_date ? $appointment->appointment_date->format('M d, Y h:i A') : '') . '.',
                    'is_read' => false,
                    'data' => [
                        'appointment_id' => $appointment->id,
                        'patient_id' => $appointment->patient_id,
                        'status' => $appointment->status,
                    ],
                ]);
            }
        } catch (\Exception $e) { /* ignore notification failure */ }

        // If an admin cancelled the appointment, mark the slot as blocked so it cannot be booked
        try {
            if ($actor && ($actor->role ?? null) === 'admin' && $appointment->appointment_date) {
                $dateKey = $appointment->appointment_date->format('Y-m-d');
                $timeKey = $appointment->appointment_date->format('H:i');
                $cacheKey = 'blocked_slots_' . $dateKey;
                $blocked = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
                if (!in_array($timeKey, $blocked)) {
                    $blocked[] = $timeKey;
                    // store blocked slots indefinitely (or until admin clears)
                    \Illuminate\Support\Facades\Cache::forever($cacheKey, $blocked);
                }
            }
        } catch (\Exception $e) {
            // don't fail cancellation if cache write fails
        }

        // Ensure cached admin list is invalidated
        try { Cache::forget('appointments_all'); } catch (\Exception $e) { }

        return $appointment;
    }

    /**
     * Get all appointments (for admin)
     */
    public function getAllAppointments(Request $request)
    {
        try {
            $user = $request->user();

            // Ensure user is authenticated
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            // Use a short-term cache to reduce DB load under heavy admin polling
            $appointments = Cache::remember('appointments_all', now()->addSeconds(5), function () {
                return Appointment::with(['patient:id,name,email', 'dentist:id,name'])
                    ->orderBy('appointment_date', 'desc')
                    ->get();
            });

            // Format the response
            // Preload services to avoid N+1 queries
            $serviceNames = $appointments->pluck('service_name')->unique()->values()->toArray();
            $serviceMap = Service::whereIn('name', $serviceNames)->get()->keyBy('name');

            $formattedAppointments = $appointments->map(function ($appointment) use ($serviceMap) {
                $service = $serviceMap->get($appointment->service_name);

                // Compute total price if missing
                $computedTotal = null;
                if ($appointment->total_price || $appointment->total_price === 0) {
                    $computedTotal = $appointment->total_price;
                } else {
                    if ($service && !str_contains($appointment->service_name, ',')) {
                        $computedTotal = $service->price;
                    } else {
                        $names = array_map('trim', explode(',', $appointment->service_name ?: ''));
                        $sum = 0; $found = false;
                        foreach ($names as $n) {
                            if ($n === '') continue;
                            $s = $serviceMap->get($n);
                            if ($s && $s->price !== null) { $sum += (float) $s->price; $found = true; }
                        }
                        if ($found) $computedTotal = $sum;
                    }
                }

                $wasTotalNull = $appointment->total_price === null;
                if ($wasTotalNull && $computedTotal !== null) {
                    try { $appointment->total_price = $computedTotal; $appointment->save(); } catch (\Exception $e) {}
                }

                $priceIsEstimated = $wasTotalNull && ($computedTotal !== null);

                return [
                    'id' => $appointment->id,
                    'service_name' => $appointment->service_name,
                    'service' => $service ? [ 'name' => $service->name, 'price' => $service->price ] : null,
                    'price' => $service ? $service->price : null,
                    'total_price' => $computedTotal,
                    'price_is_estimated' => $priceIsEstimated,
                    'description' => $appointment->description,
                    'appointment_date' => $appointment->appointment_date->toISOString(),
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                    'patient' => [
                        'id' => $appointment->patient->id,
                        'name' => $appointment->patient->name,
                        'email' => $appointment->patient->email,
                    ],
                    'dentist_name' => $appointment->dentist ? $appointment->dentist->name : null,
                    'created_at' => $appointment->created_at->toISOString(),
                ];
            });

            return response()->json($formattedAppointments, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch appointments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update appointment status (admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can update appointment status.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:confirmed,completed,cancelled',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $appointment = Appointment::find($id);

            if (!$appointment) {
                return response()->json([
                    'message' => 'Appointment not found'
                ], 404);
            }

            $newStatus = $request->status;

            // Prevent invalid transitions
            if ($newStatus === 'confirmed' && in_array($appointment->status, ['completed', 'cancelled'])) {
                return response()->json(['message' => 'Cannot confirm a completed or cancelled appointment'], 400);
            }
            if ($newStatus === 'completed' && $appointment->status === 'cancelled') {
                return response()->json(['message' => 'Cannot complete a cancelled appointment'], 400);
            }
            // Only allow completing appointments that are confirmed
            if ($newStatus === 'completed' && $appointment->status !== 'confirmed') {
                return response()->json(['message' => 'Only confirmed appointments can be marked as completed'], 400);
            }
            if ($newStatus === 'cancelled' && $appointment->status === 'completed') {
                return response()->json(['message' => 'Cannot cancel a completed appointment'], 400);
            }

            if ($newStatus === 'cancelled') {
                // Call shared cancellation logic so notifications match patient-cancel behavior
                $this->performCancel($appointment, $user);
            } else {
                $appointment->status = $newStatus;
                $appointment->save();

                // Create a notification for the patient for status change
                try {
                    Notification::create([
                        'user_id' => $appointment->patient_id,
                        'type' => 'appointment',
                        'title' => 'Appointment status updated',
                        'message' => 'Your appointment for ' . $appointment->service_name . ' on ' . ($appointment->appointment_date ? $appointment->appointment_date->format('M d, Y h:i A') : '') . ' is now ' . $appointment->status . '.',
                        'is_read' => false,
                        'data' => [
                            'appointment_id' => $appointment->id,
                            'status' => $appointment->status,
                        ],
                    ]);
                } catch (\Exception $e) { /* ignore notification failure */ }
            }

            // Clear cached admin appointments so admins see updates promptly
            try { Cache::forget('appointments_all'); } catch (\Exception $e) { }

            return response()->json([
                'message' => 'Appointment status updated successfully',
                'appointment' => [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update appointment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin-only cancel route that mirrors the patient cancel logic
     */
    public function adminCancel(Request $request, $id)
    {
        try {
            $user = $request->user();
            // Only admins allowed
            if (!$user || $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized. Only admins can cancel appointments via this endpoint.'], 403);
            }

            $appointment = Appointment::find($id);
            if (!$appointment) {
                return response()->json(['message' => 'Appointment not found'], 404);
            }

            // Prevent cancelling a completed appointment
            if ($appointment->status === 'completed') {
                return response()->json(['message' => 'Cannot cancel a completed appointment'], 400);
            }

            // Use shared cancellation logic and pass admin as actor
            $this->performCancel($appointment, $user);

            return response()->json([
                'message' => 'Appointment cancelled successfully',
                'appointment' => [ 'id' => $appointment->id, 'status' => $appointment->status ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([ 'message' => 'Failed to cancel appointment', 'error' => $e->getMessage() ], 500);
        }
    }

    /**
     * Admin-only refresh endpoint to clear the appointments cache and return fresh appointments
     */
    public function adminRefresh(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || $user->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized. Only admins can refresh appointments.'], 403);
            }

            try { Cache::forget('appointments_all'); } catch (\Exception $e) { }

            // Return the up-to-date appointment list
            return $this->getAllAppointments($request);
        } catch (\Exception $e) {
            return response()->json([ 'message' => 'Failed to refresh appointments', 'error' => $e->getMessage() ], 500);
        }
    }
}
