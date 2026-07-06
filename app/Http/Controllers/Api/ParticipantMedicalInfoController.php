<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParticipantMedicalInfo;
use App\Models\Attendee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ParticipantMedicalInfoController extends Controller
{
    /**
     * Store or update participant medical information
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attendeeId' => 'required|integer|exists:attendees,attendeeId',
            'hasAllergy' => 'required|boolean',
            'allergyDetails' => 'nullable|string|max:1000',
            'hasDrugAllergy' => 'required|boolean',
            'drugAllergyType' => 'nullable|string|max:500',
            'isPregnant' => 'required|boolean',
            'pregnancyMonths' => 'nullable|string|max:50',
            'isBreastfeeding' => 'required|boolean',
            'onMedications' => 'required|boolean',
            'medicationType' => 'nullable|string|max:1000',
            'onBirthControl' => 'required|boolean',
            'hasSurgicalHistory' => 'required|boolean',
            'surgicalHistoryDetails' => 'nullable|string|max:1000',
            'hasMedicalConditions' => 'required|boolean',
            'medicalConditionsDetails' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if attendee exists
            $attendee = Attendee::where('attendeeId', $request->attendeeId)->first();
            
            if (!$attendee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Participant not found',
                ], 404);
            }

            // Update or create medical info
            $medicalInfo = ParticipantMedicalInfo::updateOrCreate(
                ['attendeeId' => $request->attendeeId],
                [
                    'has_allergy' => $request->hasAllergy,
                    'allergy_details' => $request->allergyDetails,
                    'has_drug_allergy' => $request->hasDrugAllergy,
                    'drug_allergy_type' => $request->drugAllergyType,
                    'is_pregnant' => $request->isPregnant,
                    'pregnancy_months' => $request->pregnancyMonths,
                    'is_breastfeeding' => $request->isBreastfeeding,
                    'on_medications' => $request->onMedications,
                    'medication_type' => $request->medicationType,
                    'on_birth_control' => $request->onBirthControl,
                    'has_surgical_history' => $request->hasSurgicalHistory,
                    'surgical_history_details' => $request->surgicalHistoryDetails,
                    'has_medical_conditions' => $request->hasMedicalConditions,
                    'medical_conditions_details' => $request->medicalConditionsDetails,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Medical information saved successfully for {$attendee->fullName}",
                'data' => [
                    'medicalInfo' => $medicalInfo->toApiResponse(),
                    'attendee' => [
                        'attendeeId' => $attendee->attendeeId,
                        'fullName' => $attendee->fullName,
                        'uniqueId' => $attendee->uniqueId,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save medical information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get participant medical information
     */
    public function show($attendeeId)
    {
        try {
            $medicalInfo = ParticipantMedicalInfo::where('attendeeId', $attendeeId)->first();

            if (!$medicalInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No medical information found for this participant',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Medical information retrieved successfully',
                'data' => $medicalInfo->toApiResponse(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve medical information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}