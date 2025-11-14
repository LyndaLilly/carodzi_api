<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset Code</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #EDE6F5; margin:0; padding:0;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #fff; margin-top: 40px; padding: 20px; border-radius: 8px;">
                    <!-- Header -->
                    <tr>
                        <td align="center">
                            <h1 style="color: #6a1b9a; margin-bottom: 5px;">Alebaz</h1>
                            <p style="color: rgb(96, 96, 96); margin-top: 0;">Password Reset Request</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 20px 0;">
                            <p style="font-size: 16px; color: #212121;">
                                Hello {{ $buyerName ? : 'BuyerName' }},
                            </p>
                            <p style="font-size: 16px; color: #212121;">
                                You requested to reset your password. Use the code below to reset it:
                            </p>

                            <!-- Reset Code -->
                            <div style="text-align:center; margin: 30px 0;">
                                <span style="
                                    font-size: 28px;
                                    font-weight: bold;
                                    color: #6a1b9a;
                                    letter-spacing: 4px;
                                    padding: 10px 20px;
                                    border-radius: 6px;
                                    background-color: #f8d32c;
                                    display: inline-block;
                                ">
                                    {{ $resetCode }}
                                </span>
                            </div>

                            <!-- Instructions -->
                            <p style="font-size: 14px; color: rgb(96, 96, 96);">
                                This code will expire in 10 minutes.
                            </p>
                            <p style="font-size: 14px; color: rgb(96, 96, 96);">
                                If you did not request this password reset, you can safely ignore this email.  
                                If you suspect any unauthorized access, you can contact 
                                <a href="mailto:support@alebaz.com" style="color:#6a1b9a; text-decoration:none;">support@alebaz.com</a>  
                                or reset your password <a href="https://www.alebaz.com/buyer/resetpasswordemail" style="color:#6a1b9a; text-decoration:none;">here</a>.
                            </p>

                            <p style="font-size: 14px; color: rgb(96, 96, 96);">
                                Thank you,<br>
                                Alebaz Team
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
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
