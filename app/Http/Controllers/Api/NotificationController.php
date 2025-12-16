<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get patient notifications
     */
    public function getPatientNotifications(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is a patient
            if ($user->role !== 'patient') {
                return response()->json([
                    'message' => 'Unauthorized. Only patients can access this endpoint.'
                ], 403);
            }

            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            // Format the response
            $formattedNotifications = $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'is_read' => (bool) $notification->is_read,
                    'status' => $notification->is_read ? 'read' : 'unread',
                    'data' => $notification->data,
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            });

            return response()->json($formattedNotifications, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get admin notifications
     */
    public function getAdminNotifications(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            // Format the response - frontend expects 'status' field
            $formattedNotifications = $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'is_read' => (bool) $notification->is_read,
                    'status' => $notification->is_read ? 'read' : 'unread',
                    'data' => $notification->data,
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            });

            return response()->json($formattedNotifications, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();

            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->is_read = true;
            $notification->save();

            return response()->json([
                'message' => 'Notification marked as read',
                'notification' => [
                    'id' => $notification->id,
                    'is_read' => (bool) $notification->is_read,
                    'status' => $notification->is_read ? 'read' : 'unread',
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
