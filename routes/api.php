<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==========================================
// AUTHENTICATION ROUTES
// ==========================================

// Patient Registration
Route::post('/patients/register', [PatientController::class, 'register'])
    ->name('api.patients.register');

// Patient Login
Route::post('/patients/login', [PatientController::class, 'login'])
    ->name('api.patients.login');

// Admin Login
Route::post('/admin/login', [AdminController::class, 'login'])
    ->name('api.admin.login');

// ==========================================
// PATIENT ROUTES (Protected)
// ==========================================

Route::prefix('patient')->middleware('auth:sanctum')->group(function () {
    // Get Patient Appointments
    Route::get('/appointments', [AppointmentController::class, 'getPatientAppointments'])
        ->name('api.patient.appointments');
    
    // Get Booked Slots for a Specific Date (all patients, real-time)
    Route::get('/appointments/booked-slots/{date}', [AppointmentController::class, 'getBookedSlotsForDate'])
        ->name('api.patient.appointments.booked-slots');
    
        // Get Blocked Slots for a Specific Date (all patients, real-time)
        Route::get('/appointments/{date}/blocked', [AppointmentController::class, 'getBlockedSlotsForDate']);
    
    // Create Patient Appointment
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->name('api.patient.appointments.store');
    
    // Cancel Patient Appointment
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])
        ->name('api.patient.appointments.cancel');
    
    // Get Patient Notifications
    Route::get('/notifications', [NotificationController::class, 'getPatientNotifications'])
        ->name('api.patient.notifications');
    
    // Mark Notification as Read
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('api.patient.notifications.read');
    
    // Update Patient Profile
    Route::put('/profile', [PatientController::class, 'updateProfile'])
        ->name('api.patient.profile');
    
    // Change Patient Password
    Route::put('/password', [PatientController::class, 'changePassword'])
        ->name('api.patient.password');
    
    // Get Active Services for Patients
    Route::get('/services', [ServiceController::class, 'getActiveServices'])
        ->name('api.patient.services');
});

// ==========================================
// ADMIN ROUTES (Protected)
// ==========================================

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    // Get All Patients
    Route::get('/patients', [AdminController::class, 'getAllPatients'])
        ->name('api.admin.patients');
    
    // Get Single Patient
    Route::get('/patients/{id}', [AdminController::class, 'getPatient'])
        ->name('api.admin.patients.show');
    
    // Upload Patient Photo
    Route::post('/patients/{id}/photo', [AdminController::class, 'uploadPatientPhoto'])
        ->name('api.admin.patients.photo');
    
    // Update Patient
    Route::put('/patients/{id}', [AdminController::class, 'updatePatient'])
        ->name('api.admin.patients.update');
    
    // Delete Patient
    Route::delete('/patients/{id}', [AdminController::class, 'deletePatient'])
        ->name('api.admin.patients.delete');
    
    // Change Patient Password
    Route::put('/patients/{id}/password', [AdminController::class, 'changePatientPassword'])
        ->name('api.admin.patients.password');
    
    // Get Patient History
    Route::get('/patients/{id}/history', [AdminController::class, 'getPatientHistory'])
        ->name('api.admin.patients.history');
    
    // Get All Appointments
    Route::get('/appointments', [AppointmentController::class, 'getAllAppointments'])
        ->name('api.admin.appointments');
    
    // Update Appointment Status
    Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])
        ->name('api.admin.appointments.status');

    // Admin cancel appointment (mirror patient cancel behavior)
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'adminCancel'])
        ->name('api.admin.appointments.cancel');

    // Admin refresh appointments (clear cache and return fresh list)
    Route::post('/appointments/refresh', [AppointmentController::class, 'adminRefresh'])
        ->name('api.admin.appointments.refresh');
    
    // Get Admin Notifications
    Route::get('/notifications', [NotificationController::class, 'getAdminNotifications'])
        ->name('api.admin.notifications');
    
    // Mark Notification as Read
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('api.admin.notifications.read');
    
    // Update Admin Profile
    Route::put('/profile', [AdminController::class, 'updateProfile'])
        ->name('api.admin.profile');
    
    // Change Admin Password
    Route::put('/password', [AdminController::class, 'changePassword'])
        ->name('api.admin.password');
    
    // Services Management
    Route::get('/services', [ServiceController::class, 'index'])
        ->name('api.admin.services');
    
    Route::post('/services', [ServiceController::class, 'store'])
        ->name('api.admin.services.store');
    
    Route::put('/services/{id}', [ServiceController::class, 'update'])
        ->name('api.admin.services.update');

    // Update service active status only
    Route::put('/services/{id}/status', [ServiceController::class, 'updateStatus'])
        ->name('api.admin.services.status');
    
    Route::delete('/services/{id}', [ServiceController::class, 'destroy'])
        ->name('api.admin.services.destroy');
    
    // Contact Messages Management
    Route::get('/contact-messages', [\App\Http\Controllers\Api\ContactMessageController::class, 'getAllMessages'])
        ->name('api.admin.contact-messages');
    
    Route::post('/contact-messages/{id}/read', [\App\Http\Controllers\Api\ContactMessageController::class, 'markAsRead'])
        ->name('api.admin.contact-messages.read');
    
    Route::delete('/contact-messages/{id}', [\App\Http\Controllers\Api\ContactMessageController::class, 'delete'])
        ->name('api.admin.contact-messages.delete');
});

// ==========================================
// GENERAL ROUTES
// ==========================================

// Get All Patients (Alternative route - used in admin dashboard)
Route::get('/patients', [AdminController::class, 'getAllPatients'])
    ->middleware('auth:sanctum')
    ->name('api.patients');

// Get All Appointments (Alternative route - used in admin dashboard)
Route::get('/appointments', [AppointmentController::class, 'getAllAppointments'])
    ->middleware('auth:sanctum')
    ->name('api.appointments');

// Public blocked slots endpoint (no auth) so booking UI can fetch admin-blocked times
Route::get('/appointments/{date}/blocked', [AppointmentController::class, 'getBlockedSlotsForDate']);
// Default authenticated user route
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
