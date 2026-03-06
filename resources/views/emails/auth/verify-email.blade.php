@component('mail::message')
# Verify your email address

Hi {{ $userName }},

Thank you for creating a Vaultly account. Please verify your email address by clicking the button below.

@component('mail::button', ['url' => $verificationUrl, 'color' => 'primary'])
Verify Email Address
@endcomponent

This link will expire in **24 hours**.

If you did not create a Vaultly account, you can safely ignore this email.

Thanks,
The Vaultly Team
@endcomponent