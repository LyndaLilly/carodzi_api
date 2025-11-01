<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inquiry Completed</title>
    <style>
        :root {
            --primary: #6a1b9a;
            --primary-light: #9c4dcc;
            --secondary: #f8d32c;
            --text-dark: #212121;
            --text-light: #ffffff;
            --text-grey: rgb(96, 96, 96);
            --bg-light: #EDE6F5;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: var(--text-light);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .header {
            background-color: var(--primary);
            color: var(--text-light);
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .content {
            padding: 30px 20px;
        }

        .content h2 {
            color: var(--primary);
            font-size: 20px;
        }

        .content p {
            font-size: 16px;
            line-height: 1.5;
            color: var(--text-grey);
        }

        .content ul {
            list-style: none;
            padding: 0;
        }

        .content ul li {
            padding: 5px 0;
        }

        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background-color: var(--primary-light);
            color: var(--text-light);
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            padding: 15px;
            font-size: 12px;
            color: var(--text-grey);
            background-color: #f1f1f1;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Inquiry Completed</h1>
        </div>
        <div class="content">
            <h2>Hello {{ $inquiry->buyer_name ?? 'Buyer' }},</h2>

            <p>Good news! Your inquiry regarding <strong>{{ $inquiry->product->name ?? 'a service/product' }}</strong> has been marked as <strong>completed</strong> by {{ $inquiry->seller->business_name ?? 'the seller' }}.</p>

            <h3>Details of the service/product:</h3>
            <ul>
                <li><strong>Message:</strong> {{ $inquiry->message ?? 'No additional details provided' }}</li>
                <li><strong>Completed on:</strong> {{ $inquiry->completed_at ? $inquiry->completed_at->format('l, d M Y h:i A') : 'N/A' }}</li>
            </ul>

            <p>You can access your orders and track your inquiries in your dashboard:</p>
            <a href="https://www.alebaz.com" class="btn">Go to Dashboard</a>

            <p style="margin-top:30px;">Thank you for using our platform!</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Alebaz. All rights reserved.
        </div>
    </div>
</body>
</html>
