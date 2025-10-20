@component('mail::message')
# 🎉 Promotion Submitted Successfully!

Hello **{{ $seller->firstname }}**,  
Thank you for promoting your store on **alebaz.com**!

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

@if($promotion->is_active)
✅ Your promotion is now **active** on Alebaz!
@else
⏳ Your payment is pending review. Once approved, your promotion will go live.
@endif

---

@component('mail::button', ['url' => 'https://alebaz.com'])
Visit Alebaz
@endcomponent

@component('mail::button', ['url' => 'https://osita.com.ng/seller/dashboard'])
Track Your Promotion in Your Dashboard
@endcomponent

Thanks,  
**The Alebaz Team**
@endcomponent
