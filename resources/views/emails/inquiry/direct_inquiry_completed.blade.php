<!DOCTYPE html>
<html>
<head>
    <title>Inquiry Completed</title>
</head>
<body>
    <h2>Hello {{ $inquiry->buyer_name ?? 'Buyer' }},</h2>

    <p>Good news! Your inquiry regarding <strong>{{ $inquiry->product->name ?? 'a service/product' }}</strong> has been marked as <strong>completed</strong> by {{ $inquiry->seller->business_name ?? 'the seller' }}.</p>

    <h3>Details of the service/product:</h3>
    <ul>
        <li><strong>Message:</strong> {{ $inquiry->message ?? 'No additional details provided' }}</li>
      
        <li><strong>Completed on:</strong> {{ $inquiry->completed_at ? $inquiry->completed_at->format('l, d M Y h:i A') : 'N/A' }}</li>
    </ul>

    <p>Thank you for using our platform!</p>
</body>
</html>
