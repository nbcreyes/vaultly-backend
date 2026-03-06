@component('mail::message')
@if($status === 'approved')
# Your Refund Has Been Approved

Hi {{ $userName }},

Your refund of **${{ number_format($amount, 2) }}** for **{{ $productTitle }}** has been approved and processed back to your original payment method.

Please allow 3 to 5 business days for the funds to appear in your account. Your download access for this product has been revoked.
@else
# Update on Your Refund Request

Hi {{ $userName }},

After reviewing your refund request for **{{ $productTitle }}**, we are unable to approve it at this time.

@if($adminNote)
**Reason:** {{ $adminNote }}
@endif

If you believe this decision is incorrect, please contact our support team.
@endif

@component('mail::button', ['url' => $dashboardUrl])
Go to Dashboard
@endcomponent

Thanks,
The Vaultly Team
@endcomponent