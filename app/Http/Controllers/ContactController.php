<?php

namespace App\Http\Controllers;

use App\Mail\ContactMail;
use App\Models\ContactMessage;
// Removed admin notification models: contact submissions will be emailed directly to clinic inbox
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    /**
     * Save contact form message to database and notify admin
     * Messages are stored in the database and shown in admin dashboard
     */
    public function send(Request $request)
    {
        // Validate form data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        try {
            // Save message to database
            $contactMessage = ContactMessage::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'is_read' => false,
            ]);

            // Log saved message
            Log::info('Contact message saved successfully', [
                'message_id' => $contactMessage->id,
                'sender' => $validated['email'],
            ]);

            // Send email notification directly to clinic inbox configured in CONTACT_RECEIVER_EMAIL
            try {
                $clinicEmail = env('CONTACT_RECEIVER_EMAIL', config('mail.from.address') ?? env('MAIL_FROM_ADDRESS'));
                if (empty($clinicEmail)) {
                    Log::warning('No clinic email configured to receive contact messages. Skipping mail send.', [
                        'message_id' => $contactMessage->id,
                    ]);
                } else {
                    Mail::to($clinicEmail)->send(new ContactMail(
                        $validated['name'],
                        $validated['email'],
                        $validated['subject'],
                        $validated['message']
                    ));
                    Log::info('Contact email sent', ['message_id' => $contactMessage->id, 'to' => $clinicEmail]);
                }
            } catch (\Exception $mailError) {
                Log::error('Failed to send contact email', [
                    'message_id' => $contactMessage->id,
                    'error' => $mailError->getMessage(),
                ]);
            }

            // Return success response
            return redirect()->to(route('home') . '#contact')
                ->with('success', 'Thank you for your message! We will get back to you soon.');
                
        } catch (\Illuminate\Database\QueryException $e) {
            // Database-specific errors
            Log::error('Contact form database error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'sql' => $e->getSql() ?? 'N/A',
            ]);
            
            // Check if it's a table doesn't exist error
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
                return redirect()->to(route('home') . '#contact')
                    ->with('error', 'Database table not found. Please run migrations: php artisan migrate')
                    ->withInput();
            }
            
            return redirect()->to(route('home') . '#contact')
                ->with('error', 'Database error. Please contact the administrator.')
                ->withInput();
                
        } catch (\Exception $e) {
            // Log the error with full details
            Log::error('Contact form submission failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return error response
            return redirect()->to(route('home') . '#contact')
                ->with('error', 'Sorry, there was an error submitting your message. Please try again later.')
                ->withInput();
        }
    }
}

