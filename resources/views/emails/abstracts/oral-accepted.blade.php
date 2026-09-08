<x-mail::message>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name', 'International Cancer Week 2026') }}
</x-mail::header>
</x-slot:header>

<div style="text-align:center; margin-bottom: 12px;">
<span class="badge badge-oral">Abstract Accepted &middot; Oral Presentation</span>
</div>

# Congratulations{{ $authorName ? ', ' . $authorName : '' }}!

Your abstract has been accepted for an **oral presentation** at
{{ config('app.name', 'International Cancer Week 2026') }}.

<x-mail::panel>
**{{ $abstract->title }}**<br>
<span style="color:#6b7280; font-size:13px;">Reference {{ $abstract->reference }}</span>
</x-mail::panel>

<x-mail::table>
| | |
|:---|---:|
| Presentation format | **Oral** |
@if($rank)
| Overall rank | **#{{ $rank }}** |
@endif
@if($subTheme && $subThemeRank)
| Sub-theme | **{{ $subTheme }}** |
<!-- | Sub-theme rank | **#{{ $subThemeRank }}** | -->
@elseif($subTheme)
| Sub-theme | **{{ $subTheme }}** |
@endif
</x-mail::table>

Full details on scheduling, session timing, and presentation guidelines will
follow shortly from the Scientific & Abstract Committee. Please keep an eye on your inbox
for that follow-up.

We look forward to your presentation.

Warm regards,<br>
Scientific & Abstract Committee

<x-slot:subcopy>
This is an automated notification regarding submission {{ $abstract->reference }}.
If anything here looks wrong, please reply to this email and the committee will follow up.
</x-slot:subcopy>
</x-mail::message>