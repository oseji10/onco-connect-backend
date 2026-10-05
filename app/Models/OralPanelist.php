<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OralPanelist extends Model
{
    protected $fillable = ['name', 'email', 'token', 'is_active', 'last_seen_at'];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(OralScore::class, 'panelist_id');
    }

    public static function newToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('token', $token)->exists());

        return $token;
    }
}