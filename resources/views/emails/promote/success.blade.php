@component('mail::message')
# 🎉 Promotion Submitted Successfully!

Hello **{{ $seller->firstname }}**,  
Thank you for promoting your store on **Ensach.com**!

Here are your promotion details:

@component('mail::table')
| Detail | Value |
|:-------|:------|
| Plan | **{{ ucfirst($promotion->plan) }}** |
| Duration | {{ $promotion->duration }} days |
| Amount | ₦{{ number_format($promotion->amount, 2) }} |
| Payment Method | {{ ucfirst($promotion->payment_method) }} |
| Status | {{ $promotion->is_active ? 'Active' : 'Pending Approval' }} |
| Start Date | {{ $promotion->start_date->format('M d, Y') }} |
| End Date | {{ $promotion->end_date->format('M d, Y') }} |
@endcomponent

@if($promotion->payment_method === 'crypto')
💰 **Crypto Hash:** {{ $promotion->crypto_hash }}
@else
📄 **Transaction Reference:** {{ $promotion->transaction_reference }}
@endif

---

If you chose **Paystack**, your promotion is already active.  
If you paid via **Crypto**, our team will review and activate it shortly.

@component('mail::button', ['url' => config('app.url')])
Visit Ensach
@endcomponent

Thanks,  
**The Ensach Team**
@endcomponent
