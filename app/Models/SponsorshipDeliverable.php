<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipDeliverable extends Model
{
    protected $primaryKey = 'deliverableId';

    protected $fillable = [
        'sponsorshipId',
        'title',
        'status',
        'dueDate',
    ];

    protected $casts = [
        'dueDate' => 'date',
    ];

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorshipId', 'sponsorshipId');
    }

    public function toApiArray(): array
    {
        return [
            'deliverableId' => $this->deliverableId,
            'title'         => $this->title,
            'status'        => $this->status,
            'dueDate'       => optional($this->dueDate)->toDateString(),
        ];
    }
}