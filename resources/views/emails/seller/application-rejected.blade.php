@component('mail::message')
# Update on your seller application

Hi {{ $userName }},

Thank you for applying to sell on Vaultly. After reviewing your application for **{{ $storeName }}**, we are unable to approve it at this time.

**Reason provided:**

{{ $rejectionReason }}

You are welcome to address the feedback above and submit a new application when you are ready.

@component('mail::button', ['url' => $reapplyUrl, 'color' => 'primary'])
Submit a New Application
@endcomponent

If you believe this decision was made in error or have questions, please reply to this email.

Thanks,
The Vaultly Team
@endcomponent