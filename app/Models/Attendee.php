<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Attendee extends Model
{
    protected $primaryKey = 'attendeeId';

    protected $fillable = [
        'eventId',
        'uniqueId',
        'firstName',
        'lastName',
        'otherNames',
        'phoneNumber',
        'phoneCountryCode',
        'email',
        'organization',
        'gender',
        'category',

        // NEW FIELDS
        'stateOfResidence',
        'country',
        'physicallyChallenged',
        'accessibilityNeeds',
        'organizationName',
        'participationType',
        'title',
        'photoUrl',


        // REGISTRATION FIELDS
        'isRegistered',
        'registeredAt',
        'registeredBy',
        'userId',

        'isAccredited',
        'accreditedAt',
        'accreditedBy',
    ];


    protected $casts = [
        'isRegistered' => 'boolean',
        'registeredAt' => 'datetime',

        'isAccredited' => 'boolean',
        'accreditedAt' => 'datetime',

        'physicallyChallenged' => 'boolean',
    ];

    /**
     * Relationships
     */

    public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'userId');
}

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }

    public function pass(): HasOne
    {
        return $this->hasOne(EventPass::class, 'attendeeId', 'attendeeId');
    }

    public function dailyAttendances()
{
    return $this->hasMany(DailyAttendance::class, 'attendeeId', 'attendeeId');
}

public function roomAllocations()
{
    return $this->hasMany(RoomAllocation::class, 'attendeeId', 'attendeeId');
}

public function activeRoomAllocation()
{
    return $this->hasOne(RoomAllocation::class, 'attendeeId', 'attendeeId')
        ->where('status', 'active')
        ->latest('allocationId');
}

public function currentRoomAllocation()
{
    return $this->hasOne(RoomAllocation::class, 'attendeeId', 'attendeeId')
        // ->where('status', 'active')
        ->latest('allocationId');
}

public function incidents()
{
    return $this->hasMany(Incident::class, 'attendeeId', 'attendeeId');
}

public function group_color()
{
    return $this->belongsTo(Color::class, 'colorId', 'colorId');
}

public function color()
{
    return $this->belongsTo(Color::class, 'colorId', 'colorId');
}

public function colors()
{
    return $this->belongsTo(Color::class, 'colorId', 'colorId');
}

public function subCommunityLead()
{
    return $this->belongsTo(SubCL::class, 'subClId');
}

public function subcl()
{
    return $this->belongsTo(SubCL::class, 'subClId');
}



 public function eventPasses()
    {
        return $this->hasMany(EventPass::class, 'attendeeId', 'attendeeId');
    }
 
    /**
     * Get the medical information for the attendee.
     */
    public function medicalInfo()
    {
        return $this->hasOne(ParticipantMedicalInfo::class, 'attendeeId', 'attendeeId');
    }
 
    /**
     * Get the medication history for the attendee.
     */
    public function medicationHistory()
    {
        return $this->hasMany(MedicationDispensing::class, 'attendeeId', 'attendeeId')
                    ->orderBy('dispensed_at', 'desc');
    }
 
    /**
     * Check if attendee has critical medical alerts
     */
    public function hasCriticalMedicalAlerts(): bool
    {
        return $this->medicalInfo && $this->medicalInfo->hasCriticalAlerts();
    }
 
    /**
     * Get attendee by QR code serial number (searches through event passes)
     */
    public static function findByQrCode(string $qrCode): ?self
    {
        $eventPass = EventPass::where('serial_number', $qrCode)
                              ->orWhere('qr_code', $qrCode)
                              ->first();
        
        return $eventPass ? $eventPass->attendee : null;
    }


    // In your Attendee model
protected $appends = ['photo_full_url'];

public function getPhotoFullUrlAttribute(): ?string
{
    $photoPath = $this->attributes['photoUrl'] ?? null;

    return $photoPath
        ? asset('storage/' . $photoPath)
        : null;
}


public function accreditedByUser()
{
    return $this->belongsTo(User::class, 'accreditedBy', 'id');
}


public function certificates(): HasMany
{
    return $this->hasMany(Certificate::class, 'attendeeId', 'attendeeId');
}


}