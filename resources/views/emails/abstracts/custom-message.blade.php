<x-mail::message>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name', 'International Cancer Week 2026') }}
</x-mail::header>
</x-slot:header>

# Hello{{ $authorName ? ', ' . $authorName : '' }},

<x-mail::panel>
**{{ $abstract->title }}**<br>
<span style="color:#6b7280; font-size:13px;">Reference {{ $abstract->reference }}</span>
</x-mail::panel>

@foreach(preg_split('/\n{2,}/', trim($body)) as $paragraph)
{{ $paragraph }}

@endforeach

Warm regards,<br>
Scientific & Abstract Committee

<x-slot:subcopy>
This message was sent regarding submission {{ $abstract->reference }}.
If you have questions, please reply to this email.
</x-slot:subcopy>
</x-mail::message>