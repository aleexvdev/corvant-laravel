<x-mail::message>
# Verify your email

Please confirm your email address. Use the button below if available, or copy the token.

@if ($url)
<x-mail::button :url="$url">
Verify email
</x-mail::button>
@endif

**Token:** {{ $token }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
