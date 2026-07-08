<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Speaker extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'session_type',
        'sub_theme',
        'session_title',
        'session_description',
        'participation_type',
        'title',
        'first_name',
        'last_name',
        'other_names',
        'organization',
        'job_title',
        'bio',
        'physically_challenged',
        'accessibility_needs',
        'email',
        'country',
        'state',
        'phone_country_code',
        'phone_number',
        'linkedin_url',
        'twitter_handle',
        'photo_path',
        'cv_path',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'physically_challenged' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}