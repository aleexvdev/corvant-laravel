<x-mail::message>
# Password reset

You requested a password reset. Use the button below if available, or copy the token.

@if ($url)
<x-mail::button :url="$url">
Reset password
</x-mail::button>
@endif

**Token:** {{ $token }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
