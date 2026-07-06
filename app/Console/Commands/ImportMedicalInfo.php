<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ParticipantMedicalInfo;
use App\Models\Attendee;
use App\Models\EventPass;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMedicalInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medical:import {file?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import participant medical information from Excel file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get file path from argument or use default
        $filePath = $this->argument('file');
        
        if (!$filePath) {
            // Try multiple default locations with the correct filename
            $filename = 'WIMA-ISSAM TRAINING CAMP - PARTICIPANTS HEALTH & SAFETY INFORMATION FORM_023354.xlsx';
            
            $possiblePaths = [
                storage_path('app/imports/' . $filename),
                storage_path('app/' . $filename),
                base_path($filename),
                public_path($filename),
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $filePath = $path;
                    $this->info("✓ Found file at: {$path}");
                    break;
                }
            }

            if (!$filePath) {
                $this->error('Excel file not found in default locations.');
                $this->newLine();
                $this->info('Searched in:');
                foreach ($possiblePaths as $path) {
                    $this->line("  - {$path}");
                }
                $this->newLine();
                $this->info('Please specify the file path:');
                $this->comment('  php artisan medical:import "/path/to/WIMA-ISSAM TRAINING CAMP - PARTICIPANTS HEALTH & SAFETY INFORMATION FORM_023354.xlsx"');
                $this->newLine();
                $this->warn('Note: Use quotes around the path if it contains spaces!');
                return 1;
            }
        }

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            $this->newLine();
            $this->warn('Tip: If the path has spaces, wrap it in quotes:');
            $this->comment('  php artisan medical:import "/Users/macbookpro/Downloads/WIMA-ISSAM TRAINING CAMP - PARTICIPANTS HEALTH & SAFETY INFORMATION FORM_023354.xlsx"');
            return 1;
        }

        $this->info("Loading Excel file from: {$filePath}");
        $this->newLine();

        try {
            // Load the spreadsheet
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Display headers for confirmation
            $this->info("Column Headers:");
            foreach ($rows[0] as $index => $header) {
                $columnLetter = $this->getColumnLetter($index);
                $this->line("  Column {$columnLetter}: {$header}");
            }
            $this->newLine();

            // Ask for confirmation
            if (!$this->confirm('Does the file structure look correct?', true)) {
                $this->warn('Import cancelled.');
                return 1;
            }

            // Remove header row
            array_shift($rows);
            
            $this->info("Processing " . count($rows) . " medical records...");
            $this->newLine();
            
            $imported = 0;
            $skipped = 0;
            $notFound = [];
            $errors = [];
            $progressBar = $this->output->createProgressBar(count($rows));

            foreach ($rows as $rowIndex => $row) {
                // Skip empty rows
                if (empty($row[0])) {
                    $progressBar->advance();
                    continue;
                }

                try {
                    $qrCodeSerial = trim($row[0]); // Column A: QR Code Serial Number
                    
                    // Find the event pass by serial number
                    $eventPass = EventPass::where('serialNumber', $qrCodeSerial)
                                        //   ->orWhere('qr_code', $qrCodeSerial)
                                          ->first();

                    if (!$eventPass) {
                        $notFound[] = $qrCodeSerial;
                        $skipped++;
                        $progressBar->advance();
                        continue;
                    }

                    // Get the attendee from the event pass
                    $attendee = $eventPass->attendee;

                    if (!$attendee) {
                        $notFound[] = $qrCodeSerial . ' (no attendee linked)';
                        $skipped++;
                        $progressBar->advance();
                        continue;
                    }

                    // Parse the medical information
                    $hasAllergy = $this->parseYesNo($row[1] ?? '');
                    $allergyDetails = $hasAllergy ? $this->parseAllergyDetails($row[1] ?? '') : null;
                    
                    $hasDrugAllergy = $this->parseYesNo($row[2] ?? '');
                    $drugAllergyType = $hasDrugAllergy ? trim($row[3] ?? '') : null;
                    
                    $isPregnant = $this->parseYesNo($row[4] ?? '');
                    $pregnancyMonths = $isPregnant ? trim($row[5] ?? '') : null;
                    
                    $isBreastfeeding = $this->parseYesNo($row[6] ?? '');
                    
                    $onMedications = $this->parseYesNo($row[7] ?? '');
                    $medicationType = $onMedications ? trim($row[8] ?? '') : null;
                    
                    $onBirthControl = $this->parseYesNo($row[9] ?? '');
                    
                    $hasSurgicalHistory = $this->parseYesNo($row[10] ?? '');
                    
                    $hasMedicalConditions = $this->parseYesNo($row[11] ?? '');
                    $medicalConditionsDetails = $hasMedicalConditions ? 
                        $this->parseMedicalConditions($row[11] ?? '') : null;

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

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($rowIndex + 2) . " ({$row[0]}): " . $e->getMessage();
                    $skipped++;
                }
                
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display summary
            $this->info('═══════════════════════════════════════════════');
            $this->info('Import Summary');
            $this->info('═══════════════════════════════════════════════');
            $this->info("✓ Successfully imported: {$imported}");
            
            if ($skipped > 0) {
                $this->warn("✗ Skipped: {$skipped}");
            }
            
            if (count($notFound) > 0) {
                $this->newLine();
                $this->warn('QR codes not found in event_passes table:');
                foreach (array_slice($notFound, 0, 20) as $qr) {
                    $this->line("  - {$qr}");
                }
                if (count($notFound) > 20) {
                    $this->warn("  ... and " . (count($notFound) - 20) . " more");
                }
                $this->newLine();
                $this->info('💡 Tip: Make sure these QR codes exist in your event_passes table');
            }
            
            if (count($errors) > 0) {
                $this->newLine();
                $this->error('Errors encountered:');
                foreach (array_slice($errors, 0, 10) as $error) {
                    $this->line("  - {$error}");
                }
                if (count($errors) > 10) {
                    $this->error("  ... and " . (count($errors) - 10) . " more");
                }
            }
            
            $this->info('═══════════════════════════════════════════════');

            if ($imported > 0) {
                $this->newLine();
                $this->info("🎉 Import completed successfully!");
            }

            return 0;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error("Error importing data: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Get Excel column letter from index
     */
    private function getColumnLetter($index)
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = floor($index / 26) - 1;
        }
        return $letter;
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
        
        if (strtolower($value) === 'yes') {
            return null;
        }

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
        
        if (strtolower($value) === 'yes') {
            return 'Kidney disease, heart conditions, hypertension, or diabetes';
        }

        if (stripos($value, 'yes,') === 0) {
            return trim(substr($value, 4));
        }

        return $value;
    }
}