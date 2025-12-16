<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

/**
 * ContactMail Mailable Class
 * 
 * This class handles sending contact form emails to las.jan25@gmail.com
 * 
 * Usage:
 * Mail::to('las.jan25@gmail.com')->send(new ContactMail($name, $email, $subject, $message));
 * 
 * The email is sent via ContactController::send() method when the contact form is submitted.
 */
class ContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $email;
    public $subject;
    public $body;

    /**
     * Create a new message instance.
     * 
     * @param string $name Sender's name
     * @param string $email Sender's email address
     * @param string $subject Email subject
     * @param string $message Message content
     */
    public function __construct($name, $email, $subject, $body)
    {
        $this->name = $name;
        $this->email = $email;
        $this->subject = $subject;
        $this->body = $body;
    }

    /**
     * Get the message envelope.
     * Sets the email subject and reply-to address.
     * 
     * @return Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Contact Message: ' . $this->subject,
            replyTo: [
                new Address($this->email, $this->name),
            ],
        );
    }

    /**
     * Get the message content definition.
     * Uses the contact-message.blade.php template located at resources/views/emails/contact-message.blade.php
     * 
     * @return Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
