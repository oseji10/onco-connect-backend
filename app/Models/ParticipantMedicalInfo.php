<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipantMedicalInfo extends Model
{
    use HasFactory;

    protected $table = 'participant_medical_info';

    protected $fillable = [
        'attendeeId',
        'has_allergy',
        'allergy_details',
        'has_drug_allergy',
        'drug_allergy_type',
        'is_pregnant',
        'pregnancy_months',
        'is_breastfeeding',
        'on_medications',
        'medication_type',
        'on_birth_control',
        'has_surgical_history',
        'surgical_history_details',
        'has_medical_conditions',
        'medical_conditions_details',
    ];

    protected $casts = [
        'has_allergy' => 'boolean',
        'has_drug_allergy' => 'boolean',
        'is_pregnant' => 'boolean',
        'is_breastfeeding' => 'boolean',
        'on_medications' => 'boolean',
        'on_birth_control' => 'boolean',
        'has_surgical_history' => 'boolean',
        'has_medical_conditions' => 'boolean',
    ];

    /**
     * Get the attendee that owns the medical information.
     */
    public function attendee()
    {
        return $this->belongsTo(Attendee::class, 'attendeeId', 'attendeeId');
    }

    /**
     * Check if participant has any critical medical alerts
     */
    public function hasCriticalAlerts(): bool
    {
        return $this->has_drug_allergy || 
               $this->is_pregnant || 
               $this->has_medical_conditions;
    }

    /**
     * Get formatted medical info for API response
     */
    public function toApiResponse(): array
    {
        return [
            'hasAllergy' => $this->has_allergy,
            'allergyDetails' => $this->allergy_details,
            'hasDrugAllergy' => $this->has_drug_allergy,
            'drugAllergyType' => $this->drug_allergy_type,
            'isPregnant' => $this->is_pregnant,
            'pregnancyMonths' => $this->pregnancy_months,
            'isBreastfeeding' => $this->is_breastfeeding,
            'onMedications' => $this->on_medications,
            'medicationType' => $this->medication_type,
            'onBirthControl' => $this->on_birth_control,
            'hasSurgicalHistory' => $this->has_surgical_history,
            'surgicalHistoryDetails' => $this->surgical_history_details,
            'hasMedicalConditions' => $this->has_medical_conditions,
            'medicalConditionsDetails' => $this->medical_conditions_details,
        ];
    }
}