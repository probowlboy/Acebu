<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Form Message</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px;">
        <h2 style="color: #4f46e5; margin-top: 0;">New Contact Form Message</h2>
        
        <div style="background-color: white; padding: 20px; border-radius: 5px; margin-top: 20px;">
            <p><strong>Name:</strong> {{ $name }}</p>
            <p><strong>Email:</strong> {{ $email }}</p>
            <p><strong>Subject:</strong> {{ $subject }}</p>
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                <p><strong>Message:</strong></p>
                <p style="white-space: pre-wrap; background-color: #f9fafb; padding: 15px; border-radius: 5px;">{{ $message }}</p>
            </div>
        </div>
        
        <p style="margin-top: 20px; font-size: 12px; color: #6b7280;">
            This message was sent from the Acebu Dental Solutions contact form.
        </p>
    </div>
</body>
</html>

