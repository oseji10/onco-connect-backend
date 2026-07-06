<x-mail::message>
# Your Event Pass — {{ $event->title }}

Dear {{ $attendee->title }} {{ $attendee->firstName }} {{ $attendee->lastName }},

Thank you for registering for **{{ $event->title }}**. Your event pass is attached.

@if($plainPassword)
<x-mail::panel>
**Your Portal Login Credentials**

You can use these to log in to the conference portal to download materials and retrieve your pass at any time.

**Email:** {{ $attendee->email }}
**Password:** `{{ $plainPassword }}`

*For security, please change your password after your first login.*
</x-mail::panel>
@endif

<x-mail::panel>
**Pass Serial:** {{ $pass->serialNumber }}
**Participation:** {{ $attendee->participationType }}
**Category:** {{ ucfirst(str_replace('_', ' ', $attendee->category)) }}
</x-mail::panel>

Present the attached QR code pass at the ICW venue for check-in.

Thanks,
The NICRAT Team
</x-mail::message>