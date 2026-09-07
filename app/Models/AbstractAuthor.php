<?php

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
         'name',
         'affiliation',
         'email',
         'phone',
         'is_corresponding',
         'order',
         ];
         
         protected $casts = [
             'is_corresponding' => 'boolean',
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