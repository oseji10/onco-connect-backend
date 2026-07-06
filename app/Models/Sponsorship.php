<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sponsorship extends Model
{
    protected $primaryKey = 'sponsorshipId';

    protected $fillable = [
        'eventId',
        'type',
        'organizationName',
        'website',
        'logoUrl',
        'description',
        'tier',
        'status',
        'currency',
        'agreedAmount',
        'amountPaid',
        'paymentStatus',
        'invoiceNumber',
        'invoiceDate',
        'createdBy',
    ];

    protected $casts = [
        'agreedAmount' => 'decimal:2',
        'amountPaid'   => 'decimal:2',
        'invoiceDate'  => 'date',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(SponsorshipContact::class, 'sponsorshipId', 'sponsorshipId');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(SponsorshipDeliverable::class, 'sponsorshipId', 'sponsorshipId');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SponsorshipDocument::class, 'sponsorshipId', 'sponsorshipId');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdBy', 'id');
    }

    public function toApiArray(): array
    {
        return [
            'sponsorshipId'    => $this->sponsorshipId,
            'eventId'          => $this->eventId,
            'type'             => $this->type,
            'organizationName' => $this->organizationName,
            'website'          => $this->website,
            // Relative path — the front end prefixes NEXT_PUBLIC_API_FILE_URL.
            'logoUrl'          => $this->logoUrl,
            'description'      => $this->description,
            'tier'             => $this->tier,
            'status'           => $this->status,
            'currency'         => $this->currency,
            'agreedAmount'     => $this->agreedAmount !== null ? (float) $this->agreedAmount : null,
            'amountPaid'       => $this->amountPaid !== null ? (float) $this->amountPaid : null,
            'paymentStatus'    => $this->paymentStatus,
            'invoiceNumber'    => $this->invoiceNumber,
            'invoiceDate'      => optional($this->invoiceDate)->toDateString(),
            'contacts'         => $this->contacts->map->toApiArray()->values(),
            'deliverables'     => $this->deliverables->map->toApiArray()->values(),
            'documents'        => $this->documents->map->toApiArray()->values(),
            'createdAt'        => optional($this->created_at)->toIso8601String(),
            'updatedAt'        => optional($this->updated_at)->toIso8601String(),
        ];
    }
}