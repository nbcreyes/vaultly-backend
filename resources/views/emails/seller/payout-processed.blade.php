@component('mail::message')
@if($status === 'paid')
# Your payout has been processed

Hi {{ $userName }},

Your payout request of **${{ number_format($amount, 2) }}** has been processed and sent to your PayPal account.

Please allow 1 to 3 business days for the funds to appear in your PayPal balance.
@else
# Update on your payout request

Hi {{ $userName }},

Your payout request of **${{ number_format($amount, 2) }}** could not be processed at this time.

@if($adminNote)
**Reason:** {{ $adminNote }}
@endif

The full amount has been returned to your available balance. You can submit a new payout request from your dashboard.
@endif

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'primary'])
Go to Dashboard
@endcomponent

Thanks,
The Vaultly Team
@endcomponent