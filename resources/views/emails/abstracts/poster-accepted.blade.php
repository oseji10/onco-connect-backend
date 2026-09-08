<x-mail::message>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name', 'International Cancer Week 2026') }}
</x-mail::header>
</x-slot:header>

<div style="text-align:center; margin-bottom: 12px;">
<span class="badge badge-poster">Abstract Accepted &middot; Poster Presentation</span>
</div>

# Congratulations{{ $authorName ? ', ' . $authorName : '' }}!

Your abstract has been accepted for a **poster presentation** at
{{ config('app.name', 'International Cancer Week 2026') }}.

<x-mail::panel>
**{{ $abstract->title }}**<br>
<span style="color:#6b7280; font-size:13px;">Reference {{ $abstract->reference }}</span>
</x-mail::panel>

<x-mail::table>
| | |
|:---|---:|
| Presentation format | **Poster** |
@if($subTheme)
| Sub-theme | **{{ $subTheme }}** |
@endif
</x-mail::table>

Poster presentations are a core part of the conference program and a great
way to get direct, extended feedback from attendees. Printing specifications,
board dimensions, and set-up times will follow shortly from the Scientific & Abstract
Committee.

We look forward to seeing your work on display.

Warm regards,<br>
Scientific & Abstract Committee

<x-slot:subcopy>
This is an automated notification regarding submission {{ $abstract->reference }}.
If anything here looks wrong, please reply to this email and the committee will follow up.
</x-slot:subcopy>
</x-mail::message>