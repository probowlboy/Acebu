<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    /**
     * Get all contact messages (admin only)
     */
    public function getAllMessages(Request $request)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            $messages = ContactMessage::orderBy('created_at', 'desc')
                ->get();

            // Format the response
            $formattedMessages = $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'name' => $message->name,
                    'email' => $message->email,
                    'subject' => $message->subject,
                    'message' => $message->message,
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->toISOString(),
                    'updated_at' => $message->updated_at->toISOString(),
                ];
            });

            return response()->json($formattedMessages, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch contact messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            $message = ContactMessage::findOrFail($id);
            $message->update(['is_read' => true]);

            return response()->json([
                'message' => 'Message marked as read',
                'data' => [
                    'id' => $message->id,
                    'is_read' => $message->is_read,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark message as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete message
     */
    public function delete(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            // Ensure user is an admin
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can access this endpoint.'
                ], 403);
            }

            $message = ContactMessage::findOrFail($id);
            $message->delete();

            return response()->json([
                'message' => 'Message deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete message',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

