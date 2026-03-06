@component('mail::message')
# Refund Issued on Your Product

Hi {{ $sellerName }},

A refund of **${{ number_format($amount, 2) }}** has been issued to a buyer for **{{ $productTitle }}**.

**Amount deducted from your balance:** ${{ number_format($deductedAmount, 2) }}

This amount has been deducted from your available balance. You can view the full transaction history in your seller dashboard.

@component('mail::button', ['url' => $dashboardUrl])
View Dashboard
@endcomponent

Thanks,
The Vaultly Team
@endcomponent