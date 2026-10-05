<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireResponse extends Model
{
    protected $table = 'questionnaire_responses';
    protected $primaryKey = 'responseId';
    protected $fillable = ['attendeeId', 'eventId', 'answers', 'submittedAt'];
    protected $casts = ['answers' => 'array', 'submittedAt' => 'datetime'];
}
