<?php
// app/Models/AbstractAuthor.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbstractAuthor extends Model
{
    use HasFactory;

    protected $table = 'abstract_authors';

    protected $fillable = [
        'abstract_id',
        'user_id',
        'name',
        'affiliation',
        'email',
        'phone',
        'is_corresponding',
        'order',
        'invited_at',
        'activated_at',
    ];

    protected $casts = [
        'is_corresponding' => 'boolean',
        'invited_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function abstract(): BelongsTo
    {
        return $this->belongsTo(AbstractSubmission::class, 'abstract_id');
    }
}