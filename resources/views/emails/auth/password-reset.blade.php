@component('mail::message')
# Reset your password

Hi {{ $userName }},

We received a request to reset the password for your Vaultly account. Click the button below to choose a new password.

@component('mail::button', ['url' => $resetUrl, 'color' => 'primary'])
Reset Password
@endcomponent

This link will expire in **60 minutes**.

If you did not request a password reset, you can safely ignore this email. Your password will not be changed.

Thanks,
The Vaultly Team
@endcomponent