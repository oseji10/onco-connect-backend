<x-mail::message>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name', 'International Cancer Week 2026') }}
</x-mail::header>
</x-slot:header>

<div style="text-align:center; margin-bottom: 12px;">
<span class="badge badge-declined">Submission Update</span>
</div>

# Hello{{ $authorName ? ', ' . $authorName : '' }},

Thank you for submitting your abstract to
{{ config('app.name', 'International Cancer Week 2026') }}.

<x-mail::panel>
**{{ $abstract->title }}**<br>
<span style="color:#6b7280; font-size:13px;">Reference {{ $abstract->reference }}</span>
</x-mail::panel>

After careful review, the Abstract Committee is unable to accept this
submission for presentation this year. This process is competitive, and a
decision here reflects the volume and quality of submissions received rather
than any single shortcoming in your work.

We genuinely encourage you to submit to future editions of
ICW, and thank you again
for your contribution to the field.

Warm regards,<br>
Scientific & Abstract Committee

<x-slot:subcopy>
This is an automated notification regarding submission {{ $abstract->reference }}.
If you have questions about this decision, please reply to this email.
</x-slot:subcopy>
</x-mail::message>