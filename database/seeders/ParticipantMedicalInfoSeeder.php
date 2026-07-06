<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ParticipantMedicalInfo;
use App\Models\Attendee;
use App\Models\EventPass;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ParticipantMedicalInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Path to your Excel file
        $filePath = storage_path('app/imports/WIMA-ISSAM TRAINING CAMP - PARTICIPANTS HEALTH & SAFETY INFORMATION FORM_023354.xlsx');

        if (!file_exists($filePath)) {
            $this->command->error("Excel file not found at: {$filePath}");
            $this->command->info("Please place the Excel file in storage/app/imports/ directory");
            return;
        }

        $this->command->info("Loading Excel file...");
        
        // Load the spreadsheet
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Remove header row
        $header = array_shift($rows);
        
        $this->command->info("Processing " . count($rows) . " medical records...");
        
        $imported = 0;
        $skipped = 0;
        $notFound = [];

        foreach ($rows as $index => $row) {
            // Skip empty rows
            if (empty($row[0])) {
                continue;
            }

            $qrCodeSerial = trim($row[0]); // Column A: QR Code Serial Number (e.g., WMA-000203)
            
            // Find the event pass by serial number
            $eventPass = EventPass::where('serial_number', $qrCodeSerial)
                                  ->orWhere('qr_code', $qrCodeSerial)
                                  ->first();

            if (!$eventPass) {
                $this->command->warn("Event pass not found for QR code: {$qrCodeSerial}");
                $notFound[] = $qrCodeSerial;
                $skipped++;
                continue;
            }

            // Get the attendee from the event pass
            $attendee = $eventPass->attendee;

            if (!$attendee) {
                $this->command->warn("Attendee not found for event pass: {$qrCodeSerial}");
                $notFound[] = $qrCodeSerial;
                $skipped++;
                continue;
            }

            // Parse the medical information
            $hasAllergy = $this->parseYesNo($row[1]); // Column B
            $allergyDetails = $hasAllergy ? $this->parseAllergyDetails($row[1]) : null;
            
            $hasDrugAllergy = $this->parseYesNo($row[2]); // Column C
            $drugAllergyType = $hasDrugAllergy ? trim($row[3]) : null; // Column D
            
            $isPregnant = $this->parseYesNo($row[4]); // Column E
            $pregnancyMonths = $isPregnant ? trim($row[5]) : null; // Column F
            
            $isBreastfeeding = $this->parseYesNo($row[6]); // Column G
            
            $onMedications = $this->parseYesNo($row[7]); // Column H
            $medicationType = $onMedications ? trim($row[8]) : null; // Column I
            
            $onBirthControl = $this->parseYesNo($row[9]); // Column J
            
            $hasSurgicalHistory = $this->parseYesNo($row[10]); // Column K
            
            $hasMedicalConditions = $this->parseYesNo($row[11]); // Column L
            $medicalConditionsDetails = $hasMedicalConditions ? 
                $this->parseMedicalConditions($row[11]) : null;

            // Create or update medical info
            ParticipantMedicalInfo::updateOrCreate(
                ['attendeeId' => $attendee->attendeeId],
                [
                    'has_allergy' => $hasAllergy,
                    'allergy_details' => $allergyDetails,
                    'has_drug_allergy' => $hasDrugAllergy,
                    'drug_allergy_type' => $drugAllergyType,
                    'is_pregnant' => $isPregnant,
                    'pregnancy_months' => $pregnancyMonths,
                    'is_breastfeeding' => $isBreastfeeding,
                    'on_medications' => $onMedications,
                    'medication_type' => $medicationType,
                    'on_birth_control' => $onBirthControl,
                    'has_surgical_history' => $hasSurgicalHistory,
                    'has_medical_conditions' => $hasMedicalConditions,
                    'medical_conditions_details' => $medicalConditionsDetails,
                ]
            );

            $this->command->info("✓ Imported medical info for {$attendee->full_name} (QR: {$qrCodeSerial})");
            $imported++;

            if (($imported + $skipped) % 50 == 0) {
                $this->command->info("Processed " . ($imported + $skipped) . " records...");
            }
        }

        $this->command->newLine();
        $this->command->info("═══════════════════════════════════════════════");
        $this->command->info("Import Summary");
        $this->command->info("═══════════════════════════════════════════════");
        $this->command->info("✓ Successfully imported: {$imported}");
        $this->command->warn("✗ Skipped (not found): {$skipped}");
        
        if (count($notFound) > 0) {
            $this->command->newLine();
            $this->command->warn("QR codes not found in system:");
            foreach (array_slice($notFound, 0, 20) as $qr) {
                $this->command->warn("  - {$qr}");
            }
            if (count($notFound) > 20) {
                $this->command->warn("  ... and " . (count($notFound) - 20) . " more");
            }
        }
        
        $this->command->info("═══════════════════════════════════════════════");
    }

    /**
     * Parse yes/no values from Excel
     */
    private function parseYesNo($value): bool
    {
        if (empty($value)) {
            return false;
        }

        $value = strtolower(trim($value));
        
        // Handle various "yes" formats
        return in_array($value, ['yes', 'yes,', 'yes.', '1', 'true']) || 
               str_starts_with($value, 'yes');
    }

    /**
     * Parse allergy details from the value
     */
    private function parseAllergyDetails($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);
        
        // If it's just "yes", return null (no specific details)
        if (strtolower($value) === 'yes') {
            return null;
        }

        // Extract details after "yes, " or "yes,"
        if (stripos($value, 'yes,') === 0) {
            return trim(substr($value, 4));
        }

        return $value;
    }

    /**
     * Parse medical conditions details
     */
    private function parseMedicalConditions($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);
        
        // If it's just "yes", return generic message
        if (strtolower($value) === 'yes') {
            return 'Kidney disease, heart conditions, hypertension, or diabetes';
        }

        // Extract specific conditions after "yes, "
        if (stripos($value, 'yes,') === 0) {
            return trim(substr($value, 4));
        }

        return $value;
    }
}