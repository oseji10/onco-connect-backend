<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireQuestion extends Model
{
    protected $table = 'questionnaire_questions';
    protected $primaryKey = 'questionId';
    protected $fillable = ['eventId', 'type', 'prompt', 'options', 'required', 'sortOrder'];
    protected $casts = ['options' => 'array', 'required' => 'boolean'];
}
