<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Color extends Model
{
    protected $table = 'colors';

    protected $primaryKey = 'colorId';

// Color.php model
public function attendees()
{
    return $this->hasMany(Attendee::class, 'colorId');
}

public function subCommunityLeaders()
{
    return $this->hasMany(SubCommunityLeader::class, 'colorId');
}

public function communityLeader()
{
    return $this->belongsTo(User::class, 'communityLeaderId');
}





}