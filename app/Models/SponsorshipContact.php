<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipContact extends Model
{
    protected $primaryKey = 'contactId';

    protected $fillable = [
        'sponsorshipId',
        'name',
        'role',
        'email',
        'phone',
    ];

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorshipId', 'sponsorshipId');
    }

    public function toApiArray(): array
    {
        return [
            'contactId' => $this->contactId,
            'name'      => $this->name,
            'role'      => $this->role,
            'email'     => $this->email,
            'phone'     => $this->phone,
        ];
    }
}