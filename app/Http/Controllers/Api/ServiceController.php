<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * Get all services
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            $services = Service::orderBy('name', 'asc')->get();

            return response()->json([
                'services' => $services
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active services for patients
     */
    public function getActiveServices(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is a patient
            if ($user->role !== 'patient') {
                return response()->json([
                    'message' => 'Unauthorized. Only patients can access this endpoint.'
                ], 403);
            }

            $services = Service::where('is_active', true)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json($services, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new service
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can create services.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'original_price' => 'nullable|numeric|min:0',
                'duration_minutes' => 'nullable|integer|min:15',
                'clinic_name' => 'nullable|string|max:50|in:Clinic 1,Clinic 2',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $service = Service::create([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'original_price' => $request->original_price,
                'duration_minutes' => $request->duration_minutes ?? 60,
                'clinic_name' => $request->clinic_name,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'message' => 'Service created successfully',
                'service' => $service
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a service
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can update services.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'original_price' => 'nullable|numeric|min:0',
                'duration_minutes' => 'nullable|integer|min:15',
                'clinic_name' => 'nullable|string|max:50|in:Clinic 1,Clinic 2',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $service = Service::find($id);

            if (!$service) {
                return response()->json([
                    'message' => 'Service not found'
                ], 404);
            }

            $service->update([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'original_price' => $request->has('original_price') ? $request->original_price : $service->original_price,
                'duration_minutes' => $request->duration_minutes ?? $service->duration_minutes,
                'clinic_name' => $request->has('clinic_name') ? $request->clinic_name : $service->clinic_name,
                'is_active' => $request->has('is_active') ? $request->is_active : $service->is_active,
            ]);

            return response()->json([
                'message' => 'Service updated successfully',
                'service' => $service
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update only the active status of a service
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can update services.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'is_active' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $service = Service::find($id);

            if (!$service) {
                return response()->json([
                    'message' => 'Service not found'
                ], 404);
            }

            $service->is_active = $request->is_active;
            $service->save();

            return response()->json([
                'message' => 'Service status updated successfully',
                'service' => $service
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update service status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a service
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can delete services.'
                ], 403);
            }

            $service = Service::find($id);

            if (!$service) {
                return response()->json([
                    'message' => 'Service not found'
                ], 404);
            }

            $service->delete();

            return response()->json([
                'message' => 'Service deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete service',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
