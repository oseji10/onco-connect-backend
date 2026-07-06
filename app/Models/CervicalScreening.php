<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CervicalScreening extends Model
{
    protected $table = 'cervical_screenings';

    protected $primaryKey = 'screeningId';
    protected $fillable = [
        'visitId',
        'method',
        'screeningDate',
        'result',
        'hpvResult',
        'hpvGenotype',
        'colposcopyDone',
        'biopsyDone',
        'biopsyResult',
        'treatmentProvided',
        'referralCompleted',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'colposcopyDone' => 'boolean',
        'biopsyDone' => 'boolean',
        'treatmentProvided' => 'boolean',
        'referralCompleted' => 'boolean',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId');
    }
}