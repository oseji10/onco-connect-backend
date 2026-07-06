<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColorectalScreening extends Model
{
    protected $table = 'colorectal_screenings';

    protected $primaryKey = 'screeningId';
    protected $fillable = [
        'visitId',
        'method',
        'screeningDate',
        'result',
        'polypDetected',
        'histologyResult',
        'treatmentReferral',
        'treatmentProvided',
    ];

    protected $casts = [
        'screeningDate' => 'date',
        'polypDetected' => 'boolean',
        'treatmentProvided' => 'boolean',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ScreeningVisit::class, 'visitId');
    }
}