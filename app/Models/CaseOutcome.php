<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseOutcome extends Model
{
    protected $table = 'case_outcomes';

    protected $primaryKey = 'outcomeId';
    protected $fillable = [
        'clientId',
        'cancerConfirmed',
        'cancerType',
        'stageAtDiagnosis',
        'diagnosisDate',
        'linkageToTreatment',
        'treatmentFacility',
        'treatmentInitiatedDate',
        'treatmentCompleted',
        'treatmentOutcome',
        'followUpStatus',
        'updatedBy',
    ];

    protected $casts = [
        'cancerConfirmed' => 'boolean',
        'diagnosisDate' => 'date',
        'linkageToTreatment' => 'boolean',
        'treatmentInitiatedDate' => 'date',
        'treatmentCompleted' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId');
    }


}