<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

        public const ROLE_AUTHOR = 7;


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

    // public function getJWTCustomClaims(): array
    // {
    //     return [
    //         'facilityId' => $this->facilityId,
    //         'role' => $this->user_role?->roleName,
    //         'mustChangePassword' => $this->must_change_password,
    //     ];
    // }

    public function getJWTCustomClaims(): array
{
    return [
        'facilityId' => $this->facilityId,

        'roles' => $this->roles()
            ->pluck('roleName')
            ->values()
            ->all(),

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


public function abstractAuthors(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(AbstractAuthor::class, 'user_id');
}

// public function isAuthor(): bool
// {
//     return $this->role === 'author';
// }

/** IDs of every abstract this user is linked to as an author */
public function abstractIds(): array
{
    return AbstractAuthor::where('user_id', $this->id)->pluck('abstract_id')->all();
}




    public function isAuthor(): bool
    {
        return (int) $this->role === self::ROLE_AUTHOR;
    }

    public function getHasAbstractAuthorLinkAttribute(): bool
    {
        return $this->abstractAuthors()->exists();
    }

    /** Friendly name for the frontend */
    public function getRoleNameAttribute(): string
    {
        return match ((int) $this->role) {
            self::ROLE_AUTHOR => 'author',
            // self::ROLE_ADMIN    => 'admin',
            // self::ROLE_REVIEWER => 'reviewer',
            default => 'user',
        };
    }

    public function roles(): BelongsToMany
{
    return $this->belongsToMany(
        Role::class,
        'user_roles',
        'user_id',
        'role_id',
        'id',
        'roleId'
    );
}


public function hasRole(string $role): bool
{
    return $this->roles()
        ->where('roleName', $role)
        ->exists();
}

public function hasAnyRole(array $roles): bool
{
    return $this->roles()
        ->whereIn('roleName', $roles)
        ->exists();
}

public function roleNames(): array
{
    return $this->roles()
        ->pluck('roleName')
        ->values()
        ->all();
}

}