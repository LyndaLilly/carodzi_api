<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Verification Code</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">
    <div style="max-width: 600px; margin: auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h2 style="color: #333;">Hello {{ $firstname }},</h2>
        <p style="font-size: 16px; color: #555;">To complete your Password Reset, please use the verification code below:</p>

        <div style="text-align: center; margin: 30px 0;">
            <span style="display: inline-block; font-size: 36px; color: #2d3748; background-color: #edf2f7; padding: 10px 20px; border-radius: 6px;">
                {{ $code }}
            </span>
        </div>

        <p style="font-size: 15px; color: #666;">Enter this code in the app to continue.</p>

        <p style="margin-top: 40px; font-size: 14px; color: #aaa;">If you didn't request for this code, you can ignore this email.</p>
    </div>
</body>
</html>
