<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    /**
     * Register a new patient
     */
    public function register(Request $request)
    {
        try {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'username' => 'required|string|min:4|max:255|unique:users',
                'password' => 'required|string|min:8',
                'gender' => 'required|in:Female,Male',
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

            // Create the patient user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'birthday' => $request->birthday,
                'phone' => $request->phone,
                'country' => $request->country,
                'municipality' => $request->municipality,
                'province' => $request->province,
                'barangay' => $request->barangay,
                'zip_code' => $request->zip_code,
                'zone_street' => $request->zone_street,
                'gender' => $request->gender,
                'role' => 'patient',
            ]);

            // Generate token for the user
            $token = $user->createToken('patient-token')->plainTextToken;

            return response()->json([
                'message' => 'Patient registered successfully',
                'patient' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'token' => $token,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login a patient
     */
    public function login(Request $request)
    {
        try {
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

            // Find user by username
            $user = User::where('username', $request->username)->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid username or password'
                ], 401);
            }

            // Check if user is a patient
            if ($user->role !== 'patient') {
                return response()->json([
                    'message' => 'Access denied. This account is not a patient account.'
                ], 403);
            }

            // Revoke all existing tokens (optional - for security)
            $user->tokens()->delete();

            // Generate new token
            $token = $user->createToken('patient-token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'patient' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'birthday' => $user->birthday,
                    'phone' => $user->phone,
                ],
                'token' => $token,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update patient profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is a patient
            if ($user->role !== 'patient') {
                return response()->json([
                    'message' => 'Unauthorized. Only patients can update their profile.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'username' => 'required|string|min:4|max:255|unique:users,username,' . $user->id,
                'birthday' => 'required|date|before:today',
                'gender' => 'required|in:Female,Male',
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
                'gender' => $request->gender,
            ]);

            return response()->json([
                'message' => 'Profile updated successfully',
                'patient' => [
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
     * Change patient password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is a patient
            if ($user->role !== 'patient') {
                return response()->json([
                    'message' => 'Unauthorized. Only patients can change their password.'
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
}
