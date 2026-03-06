@component('mail::message')
# Congratulations, your application was approved

Hi {{ $userName }},

Great news. Your application to sell on Vaultly has been approved. Your store **{{ $storeName }}** is now active and you can start listing products immediately.

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'primary'])
Go to Seller Dashboard
@endcomponent

Here is what you can do next:

- Complete your store profile by adding a logo, banner, and description
- Create your first product listing
- Share your store page with your audience

If you have any questions, reply to this email and our team will help you.

Thanks,
The Vaultly Team
@endcomponent