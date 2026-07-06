<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRiskProfile extends Model
{
    protected $table = 'client_risk_profiles';

    protected $primaryKey = 'riskProfileId';
    protected $fillable = [
        'clientId',
        'familyHistory',
        'smokingStatus',
        'alcoholConsumption',
        'weightKg',
        'heightCm',
        'bmi',
        'hivStatus',
        'hbvStatus',
        'hcvStatus',
        'comorbiditiesJson',
        'recordedAt',
        'recordedBy',
    ];

    protected $casts = [
        'familyHistory' => 'boolean',
        'comorbiditiesJson' => 'array',
        'recordedAt' => 'datetime',
    ];

    public function uniqueIds()
    {
        return ['riskProfileId'];
    }
    
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recordedBy');
    }
}