<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Changed Successfully</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #EDE6F5; margin:0; padding:0;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #fff; margin-top: 40px; padding: 20px; border-radius: 8px;">
                    <tr>
                        <td align="center">
                            <h1 style="color: #6a1b9a; margin-bottom: 5px;">Alebaz</h1>
                            <p style="color: rgb(96, 96, 96); margin-top: 0;">Password Reset Successfully</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 0;">
                            <p style="font-size: 16px; color: #212121;">
                                Hello {{ $buyerName ?: 'buyer' }},
                            </p>
                            <p style="font-size: 16px; color: #212121;">
                                Your password was successfully Reset. If you made this change, you can safely ignore this message.
                            </p>
                            <p style="font-size: 16px; color: #212121;">
                                If you did <strong>not</strong> request this change, please contact our support team immediately or reset your password using the link below:
                            </p>

                            <div style="text-align:center; margin: 30px 0;">
                                <a href="https://www.alebaz.com/buyer/resetpasswordemail" 
                                   style="background-color: #6a1b9a; color: #fff; padding: 10px 25px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                                    Reset Password
                                </a>
                            </div>

                            <p style="font-size: 14px; color: rgb(96, 96, 96);">
                                For further assistance, contact us at 
                                <a href="mailto:support@alebaz.com" style="color:#6a1b9a; text-decoration:none;">support@alebaz.com</a>.
                            </p>

                            <p style="font-size: 14px; color: rgb(96, 96, 96);">
                                Thank you,<br>
                                Alebaz Security Team
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding-top: 20px;">
                            <p style="font-size: 12px; color: rgb(96, 96, 96);">
                                &copy; {{ date('Y') }} Alebaz. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
