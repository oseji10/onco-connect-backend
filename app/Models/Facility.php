<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    protected $table = 'facilities';

    protected $primaryKey = 'facilityId';

    protected $fillable = [
        'facilityName',
        'facilityCode',
        'facilityState',
        'facilityLga',
        'facilityAddress',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'createdBy');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'facilityId');
    }

    public function screeningVisits(): HasMany
    {
        return $this->hasMany(ScreeningVisit::class, 'facilityId');
    }
}