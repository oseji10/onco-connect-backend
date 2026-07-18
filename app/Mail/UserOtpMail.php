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
        'must_change_password',
        'otp',
        'otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    protected $casts = [
        'must_change_password' => 'boolean',
        'otp_expires_at' => 'datetime',
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
            'mustChangePassword' => $this->must_change_password,
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_role?->roleName === 'super_admin';
    }

    public function partner(): bool
    {
        return $this->user_role?->roleName === 'partner';
    }
}