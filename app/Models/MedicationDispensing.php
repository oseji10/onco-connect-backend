<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * Medication Dispensing Model
 * Tracks medication dispensed to participants
 */
class MedicationDispensing extends Model
{
    protected $table = 'medication_dispensing';
    protected $primaryKey = 'dispensingId';
 
    protected $fillable = [
        'eventId',
        'attendeeId',
        'supplyId',
        'drugName',
        'quantityDispensed',
        'recipientName',
        'recipientType',
        'recipientNotes',
        'symptoms',
        'instructions',
        'dispensedBy',
        'deviceName',
    ];
 
    protected $casts = [
        'quantityDispensed' => 'integer',
    ];
 
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }
 
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }
 
    public function supply(): BelongsTo
    {
        return $this->belongsTo(MedicationSupply::class, 'supplyId', 'supplyId');
    }
 
    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensedBy', 'id');
    }
 
    /**
     * Check if this dispensing was to a participant
     */
    public function isParticipant(): bool
    {
        return !empty($this->attendeeId);
    }
 
    /**
     * Get recipient display name
     */
    public function getRecipientNameAttribute(): string
    {
        if ($this->isParticipant() && $this->attendee) {
            return $this->attendee->fullName;
        }
        
        return $this->attributes['recipientName'] ?? 'Unknown';
    }
}