<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiverScreening extends Model
{
    protected $table = 'liver_screenings';

    protected $primaryKey = 'screeningId';
    protected $fillable = [
        'visitId',
        'method',
        'screeningDate',
        'result',
        'hbvStatus',
        'hcvStatus',
        'afpValue',
        'lesionDetected',
        'treatmentReferral',
        'treatmentProvided',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'lesionDetected' => 'boolean',
        'treatmentProvided' => 'boolean',
    ];



    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId');
    }
}