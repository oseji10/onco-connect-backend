<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CL extends Model
{
protected $table = 'cls';
protected $primaryKey = 'clId';

    protected $fillable = [
        'state',
        'lga',
    ];

    
}