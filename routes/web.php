<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::post('/contact/send', [ContactController::class, 'send'])->name('contact.send');

Route::get('/patient/login', function () {
    return view('patient.login');
})->name('patient.login');

Route::get('/patient/signup', function () {
    return view('patient.signup');
})->name('patient.signup');

Route::get('/admin/login', function () {
    return view('admin.login');
})->name('admin.login');

// ==========================================
// PATIENT ROUTES
// ==========================================
Route::prefix('patient')->group(function () {
    Route::get('/patientdashboard', function () {
        return view('patient.dashboard');
    })->name('patient.dashboard');
    
    Route::get('/patientappointment', function () {
        return view('patient.appointments');
    })->name('patient.appointments');
    
    Route::get('/patienthistory', function () {
        return view('patient.history');
    })->name('patient.history');
    
    Route::get('/settings', function () {
        return view('patient.settings');
    })->name('patient.settings');
});

// ==========================================
// ADMIN ROUTES
// ==========================================
Route::prefix('admin')->group(function () {
    Route::get('/admindashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');
    
    Route::get('/adminappointments', function () {
        return view('admin.appointments');
    })->name('admin.appointments');
    
    Route::get('/adminusers', function () {
        return view('admin.users');
    })->name('admin.users');

    Route::get('/patients/{patientId}', function ($patientId) {
        return view('admin.patient-details', ['patientId' => $patientId]);
    })->name('admin.patients.show');
    
    Route::get('/adminservices', function () {
        return view('admin.services');
    })->name('admin.services');
    
    Route::get('/adminhistory', function () {
        return view('admin.history');
    })->name('admin.history');
    
    // Note: admin contact messages view removed. Contact form submissions are emailed to clinic inbox.

    // Notifications (Contact Messages) - index and show
    Route::get('/adminnotifications', function () {
        $messages = \App\Models\ContactMessage::orderBy('created_at', 'desc')->get();
        return view('admin.notifications', ['messages' => $messages]);
    })->name('admin.notifications');

    Route::get('/adminnotifications/{id}', function ($id) {
        $message = \App\Models\ContactMessage::findOrFail($id);
        return view('admin.notifications-show', ['message' => $message]);
    })->name('admin.notifications.show');
    
    Route::get('/settings', function () {
        return view('admin.settings');
    })->name('admin.settings');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
