<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipDocument extends Model
{
    protected $primaryKey = 'documentId';

    protected $fillable = [
        'sponsorshipId',
        'title',
        'category',
        'fileUrl',
    ];

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorshipId', 'sponsorshipId');
    }

    public function toApiArray(): array
    {
        return [
            'documentId' => $this->documentId,
            'title'      => $this->title,
            'category'   => $this->category,
            // Relative path — the front end prefixes NEXT_PUBLIC_API_FILE_URL.
            'fileUrl'    => $this->fileUrl,
            'createdAt'  => optional($this->created_at)->toIso8601String(),
        ];
    }
}