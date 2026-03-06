@component('mail::message')
# Refund Request Received

Hi {{ $userName }},

We have received your refund request for **{{ $productTitle }}** (${{ number_format($amount, 2) }}).

Our team will review your request within 1 to 2 business days and notify you of the outcome.

If you have any questions in the meantime, please reply to this email.

@component('mail::button', ['url' => $supportUrl])
Contact Support
@endcomponent

Thanks,
The Vaultly Team
@endcomponent