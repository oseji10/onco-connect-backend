<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
/**
 * Medication Supply Model
 * Stores medication inventory from pharmacy/vendors
 */
class MedicationSupply extends Model
{
    protected $table = 'medication_supplies';
    protected $primaryKey = 'supplyId';
 
    protected $fillable = [
        'eventId',
        'drugName',
        'batchNumber',
        'expiryDate',
        'quantitySupplied',
        'quantityDispensed',
        'quantityRemaining',
        'supplyDate',
        'notes',
        'recordedBy',
    ];
 
    protected $casts = [
        'expiryDate' => 'date',
        'supplyDate' => 'date',
        'quantitySupplied' => 'integer',
        'quantityDispensed' => 'integer',
        'quantityRemaining' => 'integer',
    ];
 
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'eventId');
    }
 
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recordedBy', 'id');
    }
 
    public function dispensings(): HasMany
    {
        return $this->hasMany(MedicationDispensing::class, 'supplyId', 'supplyId');
    }
 
    /**
     * Check if medication is expired
     */
    public function isExpired(): bool
    {
        return $this->expiryDate < now()->startOfDay();
    }
 
    /**
     * Check if medication is expiring soon (within 30 days)
     */
    public function isExpiringSoon(): bool
    {
        return $this->expiryDate <= now()->addDays(30)->startOfDay() && !$this->isExpired();
    }
}
 
