<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubCL extends Model
{
    protected $table = 'sub_cls';

    protected $primaryKey = 'subClId';



// SubCommunityLeader.php model
public function attendees()
{
    return $this->hasMany(Attendee::class, 'subClId');
}

public function color()
{
    return $this->belongsTo(Color::class, 'colorId');
}

public function user()
{
    return $this->belongsTo(User::class, 'userId');
}



}