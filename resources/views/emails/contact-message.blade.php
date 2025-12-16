{{--
    Email Template: Contact Message
    ===============================
    This template is used by ContactController::send() to send contact form submissions
    to las.jan25@gmail.com via the ContactMail Mailable class.
    
    Variables passed to this template:
    - $name: Sender's name
    - $email: Sender's email address
    - $subject: Email subject
    - $message: Message content
    
    The email is sent automatically when the contact form is submitted.
    See: app/Http/Controllers/ContactController.php -> send() method
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>New Contact Message</title>
</head>
<body style="font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f4f4; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 0; padding: 0; background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" style="width: 600px; max-width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 30px 30px 20px 30px; background-color: #4f46e5; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">New Contact Message</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 10px 0;">
                                        <p style="margin: 0; font-size: 14px; color: #666666;">
                                            <strong style="color: #333333; display: inline-block; min-width: 80px;">Name:</strong>
                                            <span style="color: #333333;">{{ e($name) }}</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0;">
                                        <p style="margin: 0; font-size: 14px; color: #666666;">
                                            <strong style="color: #333333; display: inline-block; min-width: 80px;">Email:</strong>
                                            <a href="mailto:{{ e($email) }}" style="color: #4f46e5; text-decoration: none;">{{ e($email) }}</a>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 0;">
                                        <p style="margin: 0; font-size: 14px; color: #666666;">
                                            <strong style="color: #333333; display: inline-block; min-width: 80px;">Subject:</strong>
                                            <span style="color: #333333;">{{ e($subject) }}</span>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Message Section -->
                            <div style="margin-top: 25px; padding-top: 25px; border-top: 2px solid #e5e7eb;">
                                <p style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #333333;">Message:</p>
                                <div style="background-color: #f9fafb; padding: 20px; border-radius: 5px; border-left: 4px solid #4f46e5;">
                                    <p style="margin: 0; font-size: 14px; color: #333333; white-space: pre-wrap; word-wrap: break-word;">{{ e($body) }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; color: #6b7280; text-align: center;">
                                This message was sent from the Acebu Dental Solutions contact form.<br>
                                <span style="color: #9ca3af;">You can reply directly to this email to respond to {{ e($name) }}.</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

