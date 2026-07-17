<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'facilityId',
        'firstName',
        'lastName',
        'email',
        'phoneNumber',
        'alternatePhoneNumber',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId');
    }

     public function user_role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'roleId');
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
{
    return [
        'facilityId' => $this->facilityId,
        'role' => $this->user_role?->roleName,
    ];
}

    public function isSuperAdmin(): bool
    {
        return $this->roleName === 'super_admin';
    }

    public function partner(): bool
    {
        return $this->roleName === 'partner';
    }
}