<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Login an admin
     */
    public function login(Request $request)
    {
        try {
            // Guarantee at least one admin exists for fresh environments
            $this->ensureDefaultAdminExists();

            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find user by username (case-insensitive) or email
            $username = trim($request->username);
            
            if (empty($username)) {
                return response()->json([
                    'message' => 'Username is required'
                ], 422);
            }
            
            $user = User::where(function($query) use ($username) {
                $query->whereRaw('LOWER(username) = ?', [strtolower($username)])
                      ->orWhereRaw('LOWER(email) = ?', [strtolower($username)]);
            })->first();

            // Check if user exists
            if (!$user) {
                \Log::warning('Admin login failed - user not found', [
                    'username' => $username,
                    'ip' => $request->ip(),
                ]);
                
                return response()->json([
                    'message' => 'Invalid username or password'
                ], 401);
            }

            // Check if password is correct
            // Trim password to handle whitespace issues
            $password = trim($request->password);
            
            if (empty($password)) {
                return response()->json([
                    'message' => 'Password is required'
                ], 422);
            }
            
            // Check password with better error handling
            if (!Hash::check($password, $user->password)) {
                \Log::warning('Admin login failed - incorrect password', [
                    'username' => $username,
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ]);
                
                return response()->json([
                    'message' => 'Invalid username or password'
                ], 401);
            }

            // Check if user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Access denied. This account is not an admin account.'
                ], 403);
            }

            // Get device/browser info for token name
            $userAgent = $request->header('User-Agent', 'Unknown Device');
            $deviceName = $this->getDeviceName($userAgent);
            $ipAddress = $request->ip();
            
            // Create device-specific token name
            $tokenName = 'admin-token-' . $deviceName . '-' . substr(md5($ipAddress . $userAgent), 0, 8);
            
            // Limit to 10 active tokens per user (prevent token spam)
            $activeTokens = $user->tokens()->count();
            if ($activeTokens >= 10) {
                // Delete oldest token
                $oldestToken = $user->tokens()->oldest()->first();
                if ($oldestToken) {
                    $oldestToken->delete();
                }
            }

            // Generate new token (don't delete existing tokens - allow multiple devices)
            $token = $user->createToken($tokenName)->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'admin' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'token' => $token,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Admin login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Make sure a default admin account exists on every environment.
     */
    private function ensureDefaultAdminExists(): void
    {
        if (User::where('role', 'admin')->exists()) {
            return;
        }

        $defaults = config('auth.default_admin', []);

        if (empty($defaults)) {
            return;
        }

        User::updateOrCreate(
            ['email' => $defaults['email'] ?? 'admin@example.com'],
            [
                'name' => $defaults['name'] ?? 'Administrator',
                'username' => $defaults['username'] ?? 'admin',
                'password' => Hash::make($defaults['password'] ?? 'admin123'),
                'role' => 'admin',
                'phone' => $defaults['phone'] ?? null,
                'country' => $defaults['country'] ?? null,
                'municipality' => $defaults['municipality'] ?? null,
                'province' => $defaults['province'] ?? null,
                'barangay' => $defaults['barangay'] ?? null,
                'zip_code' => $defaults['zip_code'] ?? null,
                'zone_street' => $defaults['zone_street'] ?? null,
                'birthday' => $defaults['birthday'] ?? null,
            ]
        );
    }

    /**
     * Get device name from user agent
     */
    private function getDeviceName($userAgent)
    {
        $deviceName = 'Unknown';
        
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent)) {
            if (preg_match('/iPhone/i', $userAgent)) {
                $deviceName = 'iPhone';
            } elseif (preg_match('/iPad/i', $userAgent)) {
                $deviceName = 'iPad';
            } elseif (preg_match('/Android/i', $userAgent)) {
                $deviceName = 'Android';
            } else {
                $deviceName = 'Mobile';
            }
        } elseif (preg_match('/Windows/i', $userAgent)) {
            $deviceName = 'Windows';
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $deviceName = 'Mac';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $deviceName = 'Linux';
        }
        
        return $deviceName;
    }

    /**
     * Update admin profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can update their profile.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'username' => 'required|string|min:4|max:255|unique:users,username,' . $user->id,
                'birthday' => 'required|date|before:today',
                'phone' => 'required|string|max:20',
                'country' => 'required|string|max:255',
                'municipality' => 'required|string|max:255',
                'province' => 'required|string|max:255',
                'barangay' => 'required|string|max:255',
                'zip_code' => 'required|string|size:4',
                'zone_street' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->username,
                'birthday' => $request->birthday,
                'phone' => $request->phone,
                'country' => $request->country,
                'municipality' => $request->municipality,
                'province' => $request->province,
                'barangay' => $request->barangay,
                'zip_code' => $request->zip_code,
                'zone_street' => $request->zone_street,
            ]);

            return response()->json([
                'message' => 'Profile updated successfully',
                'admin' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'birthday' => $user->birthday,
                    'phone' => $user->phone,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change admin password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can change their password.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all patients/users
     */
    public function getAllPatients(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            $patients = User::where('role', 'patient')
                ->orderBy('created_at', 'desc')
                ->get();

            // Format the response
            $formattedPatients = $patients->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'username' => $patient->username,
                    'birthday' => $patient->birthday,
                    'phone' => $patient->phone,
                    'country' => $patient->country,
                    'municipality' => $patient->municipality,
                    'province' => $patient->province,
                    'barangay' => $patient->barangay,
                    'zip_code' => $patient->zip_code,
                    'zone_street' => $patient->zone_street,
                    'profile_photo_url' => $patient->profile_photo_url,
                    'city' => $patient->municipality,
                    'created_at' => $patient->created_at->toISOString(),
                ];
            });

            return response()->json([
                'patients' => $formattedPatients
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch patients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single patient details
     */
    public function getPatient(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            $patient = User::where('role', 'patient')->findOrFail($id);

            return response()->json([
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'username' => $patient->username,
                    'birthday' => $patient->birthday,
                    'phone' => $patient->phone,
                    'country' => $patient->country,
                    'municipality' => $patient->municipality,
                    'province' => $patient->province,
                    'barangay' => $patient->barangay,
                    'zip_code' => $patient->zip_code,
                    'zone_street' => $patient->zone_street,
                    'gender' => $patient->gender,
                    'medical_record_number' => $patient->medical_record_number,
                    'profile_photo_url' => $patient->profile_photo_url,
                    'created_at' => $patient->created_at->toISOString(),
                    'updated_at' => $patient->updated_at->toISOString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch patient',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload patient profile photo
     */
    public function uploadPatientPhoto(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can update patients.'
                ], 403);
            }

            $patient = User::where('role', 'patient')->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'photo' => 'required|image|mimes:jpeg,jpg,png|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update profile photo
            $patient->updateProfilePhoto($request->file('photo'));
            $patient->refresh();

            return response()->json([
                'message' => 'Profile photo updated successfully',
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'profile_photo_url' => $patient->profile_photo_url,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update patient information
     */
    public function updatePatient(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can update patients.'
                ], 403);
            }

            $patient = User::where('role', 'patient')->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $patient->id,
                'username' => 'sometimes|required|string|min:4|max:255|unique:users,username,' . $patient->id,
                'birthday' => 'sometimes|required|date|before:today',
                'phone' => 'sometimes|required|string|max:20',
                'country' => 'sometimes|required|string|max:255',
                'municipality' => 'sometimes|required|string|max:255',
                'province' => 'sometimes|required|string|max:255',
                'barangay' => 'sometimes|required|string|max:255',
                'zip_code' => 'sometimes|required|string|size:4',
                'zone_street' => 'sometimes|required|string|max:255',
                'photo' => 'sometimes|nullable|image|mimes:jpeg,jpg,png|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'name', 'email', 'username', 'birthday', 'phone',
                'country', 'municipality', 'province', 'barangay', 'zip_code', 'zone_street', 'medical_record_number'
            ]);

            // Handle profile photo upload
            if ($request->hasFile('photo')) {
                $patient->updateProfilePhoto($request->file('photo'));
            }

            $patient->update($updateData);

            return response()->json([
                'message' => 'Patient updated successfully',
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'username' => $patient->username,
                    'profile_photo_url' => $patient->profile_photo_url,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update patient',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete patient
     */
    public function deletePatient(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can delete patients.'
                ], 403);
            }

            $patient = User::where('role', 'patient')->findOrFail($id);
            $patient->delete();

            return response()->json([
                'message' => 'Patient deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete patient',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change patient password (admin can reset patient password)
     */
    public function changePatientPassword(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can change patient passwords.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'new_password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $patient = User::where('role', 'patient')->findOrFail($id);
            $patient->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'message' => 'Patient password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to change patient password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get patient history (appointments)
     */
    public function getPatientHistory(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            $patient = User::where('role', 'patient')->findOrFail($id);

            $appointments = Appointment::where('patient_id', $patient->id)
                ->with(['dentist:id,name'])
                ->orderBy('appointment_date', 'desc')
                ->get();

            $formattedAppointments = $appointments->map(function ($appointment) {
                // Compute total_price if missing by summing services when possible
                $computedTotal = $appointment->total_price;
                if ($computedTotal === null) {
                    $names = array_map('trim', explode(',', $appointment->service_name ?: ''));
                    $sum = 0; $found = false;
                    $services = \App\Models\Service::whereIn('name', $names)->get()->keyBy('name');
                    foreach ($names as $n) {
                        if ($n === '') continue;
                        $s = $services->get($n);
                        if ($s && $s->price !== null) { $sum += (float) $s->price; $found = true; }
                    }
                    if ($found) $computedTotal = $sum;
                }

                $wasTotalNull = $appointment->total_price === null;
                if ($wasTotalNull && $computedTotal !== null) {
                    try { $appointment->total_price = $computedTotal; $appointment->save(); } catch (\Exception $e) {}
                }

                $priceIsEstimated = ($wasTotalNull && $computedTotal !== null);

                return [
                    'id' => $appointment->id,
                    'service_name' => $appointment->service_name,
                    'description' => $appointment->description,
                    'total_price' => $computedTotal,
                    'price_is_estimated' => $priceIsEstimated,
                    'appointment_date' => $appointment->appointment_date->toISOString(),
                    'status' => $appointment->status,
                    'notes' => $appointment->notes,
                    'dentist_name' => $appointment->dentist ? $appointment->dentist->name : null,
                    'created_at' => $appointment->created_at->toISOString(),
                ];
            });

            return response()->json([
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                ],
                'appointments' => $formattedAppointments
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch patient history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
