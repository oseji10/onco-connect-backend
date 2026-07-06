<?php
// app/Models/Resource.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'status',
        'downloads',
        'uploaded_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'downloads' => 'integer',
        'approved_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── Accessors ─────────────────────────────────────────────────────

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // ─── Scopes ────────────────────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    // ─── Helper Methods ────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function canBeDownloaded(): bool
    {
        return $this->isApproved();
    }

    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function reject(): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function incrementDownloads(): void
    {
        $this->increment('downloads');
    }
}