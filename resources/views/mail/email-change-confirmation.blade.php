<x-mail::message>
# Confirm your new email

You requested to change the email address on your account. Use the button below if available, or copy the token.

@if ($url)
<x-mail::button :url="$url">
Confirm email change
</x-mail::button>
@endif

**Token:** {{ $token }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
