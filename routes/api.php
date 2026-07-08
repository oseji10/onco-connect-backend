<?php

use App\Http\Controllers\Api\AttendeeRegistrationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DailyAttendanceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\MealTicketController;
use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\MealSessionController;
use App\Http\Controllers\Api\EventPassController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\RiskProfileController;
use App\Http\Controllers\Api\ExitController;
use App\Http\Controllers\Api\ColorGroupsController;
use App\Http\Controllers\Api\RoomAllocationController;
use App\Http\Controllers\Api\RoomCheckinController;
use App\Http\Controllers\Api\MedicationController;
use App\Http\Controllers\Api\TicketQrController;
use App\Http\Controllers\ScannerController as ControllersScannerController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\IssamCentralDashboardController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\MealRatingStatisticsController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\AttendeeController;
use App\Http\Controllers\ResourceController;

use App\Http\Controllers\Api\ParticipantMedicalInfoController;
use App\Http\Controllers\Api\AccreditationController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\SponsorshipController;

use App\Http\Controllers\Api\AbstractSubmissionController;
use App\Http\Controllers\Api\ReviewAssignmentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ReviewerController;

use App\Http\Controllers\Api\ReviewerInvitationController;
use App\Http\Controllers\Api\SpeakerController;

Route::put('/conference/attendee/register', [AttendeeController::class, 'store']);
Route::post('/abstracts/submit', [AbstractSubmissionController::class, 'store'])
    ->middleware('throttle:10,1');

Route::get('/abstracts/reviewers/invite/{token}', [ReviewerInvitationController::class, 'show']);
Route::post('/abstracts/reviewers/invite/{token}/accept', [ReviewerInvitationController::class, 'accept'])
    ->middleware('throttle:10,1');


    Route::post('/speakers/register', [SpeakerController::class, 'store'])
    ->middleware('throttle:10,1');
 

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    
    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
        });
    });
        // Route::put('/conference/attendee/register', [AttendeeController::class, 'store']);
        
Route::middleware(['auth:api', 'facility.scope'])->group(function () {

// Admin — TODO: add role middleware once available.
    Route::get('/speakers', [SpeakerController::class, 'index']);
    Route::get('/speakers/{speaker}', [SpeakerController::class, 'show']);
    Route::patch('/speakers/{speaker}/status', [SpeakerController::class, 'updateStatus']);


Route::prefix('abstracts')->group(function () {

    // Abstracts
    Route::get('/', [AbstractSubmissionController::class, 'index']);

    // Reviewer management (static routes first)
    Route::post('/reviewers/invite', [ReviewerController::class, 'invite']);
    Route::get('/reviewers', [ReviewerController::class, 'index']);
    Route::post('/reviewers/{reviewer}/resend-invite', [ReviewerController::class, 'resendInvite']);

    // Reviewer actions
    Route::get('/reviews/assigned', [ReviewController::class, 'assigned']);

    // Abstract-specific routes (wildcards last)
    Route::get('/{abstract}', [AbstractSubmissionController::class, 'show']);
    Route::patch('/{abstract}/status', [AbstractSubmissionController::class, 'updateStatus']);
    Route::post('/{abstract}/assign-reviewers', [ReviewAssignmentController::class, 'store']);
    Route::post('/{abstract}/review', [ReviewController::class, 'store']);
});

// Reviewers


// Public routes (or authenticated routes for participants)

    // Get all resources (with pagination and filters)
    Route::get('/resources', [ResourceController::class, 'index']);
    
    // Upload a new resource
    Route::post('/resources/upload', [ResourceController::class, 'upload']);
    
    // Get categories
    Route::get('/resources/categories', [ResourceController::class, 'categories']);
    
    // Get statistics
    Route::get('/resources/stats', [ResourceController::class, 'stats']);
    
  
        Route::get('/resources/{id}', [ResourceController::class, 'show']);
        Route::get('/resources/download', [ResourceController::class, 'download']);
        
        // Admin only routes
   
            Route::patch('/resources/approve', [ResourceController::class, 'approve']);
            Route::patch('/resources/reject', [ResourceController::class, 'reject']);
            Route::delete('/resources/', [ResourceController::class, 'destroy']);

        
        // Owner can delete their own resource
        Route::delete('/resources/own', [ResourceController::class, 'destroy']);
    
            Route::get('/sponsorships', [SponsorshipController::class, 'index']);
    Route::post('/sponsorships', [SponsorshipController::class, 'store']);
    Route::match(['put', 'post'], '/sponsorships/{sponsorshipId}', [SponsorshipController::class, 'update']);
    Route::delete('/sponsorships/{sponsorshipId}', [SponsorshipController::class, 'destroy']);
    Route::patch('/sponsorships/{sponsorshipId}/status', [SponsorshipController::class, 'updateStatus']);

    Route::post('/sponsorships/{sponsorshipId}/contacts', [SponsorshipController::class, 'storeContact']);
    Route::delete('/sponsorships/{sponsorshipId}/contacts/{contactId}', [SponsorshipController::class, 'destroyContact']);

    Route::post('/sponsorships/{sponsorshipId}/deliverables/seed', [SponsorshipController::class, 'seedDeliverables']);
    Route::post('/sponsorships/{sponsorshipId}/deliverables', [SponsorshipController::class, 'storeDeliverable']);
    Route::patch('/sponsorships/{sponsorshipId}/deliverables/{deliverableId}', [SponsorshipController::class, 'updateDeliverable']);
    Route::delete('/sponsorships/{sponsorshipId}/deliverables/{deliverableId}', [SponsorshipController::class, 'destroyDeliverable']);

    Route::post('/sponsorships/{sponsorshipId}/documents', [SponsorshipController::class, 'storeDocument']);
    Route::delete('/sponsorships/{sponsorshipId}/documents/{documentId}', [SponsorshipController::class, 'destroyDocument']);


    // Accreditation routes
    Route::post('/accreditation/search', [AccreditationController::class, 'search']);
    Route::get('/accreditation/events/{eventId}/accredited-attendees', [AccreditationController::class, 'index']);
    Route::post('/accreditation/events/{eventId}/accreditations', [AccreditationController::class, 'store']);

    Route::put('/conference/register', [AttendeeController::class, 'store']);
    Route::post('/conference/participants/{attendee}/resend-pass', [AttendeeController::class, 'resendPass']);
    Route::get('/conference/participants', [AttendeeController::class, 'index']);
    Route::post('/conference/participants/{attendeeId}', [AttendeeController::class, 'update']);

        Route::get('/events/{eventId}/certificates/recipients', [CertificateController::class, 'recipients']);
    Route::post('/events/{eventId}/certificates/generate', [CertificateController::class, 'generate']);
    Route::post('/events/{eventId}/certificates/send', [CertificateController::class, 'send']);
    Route::post('/events/{eventId}/certificates/bulk-send', [CertificateController::class, 'bulkSend']);


    Route::apiResource('meals', MealController::class);
    Route::patch('/meals/{meal}/status', [MealController::class, 'updateStatus']);

    Route::post('/meals/{meal}/generate-tickets', [MealTicketController::class, 'generate']);
    Route::get('/meals/{meal}/tickets', [MealTicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [MealTicketController::class, 'show']);
    Route::patch('/tickets/{ticket}/void', [MealTicketController::class, 'void']);

    Route::get('/tickets/{ticket}/qr', [TicketQrController::class, 'show']);
    Route::post('/tickets/{ticket}/qr/regenerate', [TicketQrController::class, 'regenerate']);
    Route::get('/tickets/{ticket}/qr/download', [TicketQrController::class, 'download']);

    Route::get('/meals/{meal}/tickets/download-zip', [MealTicketController::class, 'downloadZip']);
    Route::get('/meals/{meal}/tickets/download-pdf', [MealTicketController::class, 'downloadPdf']);

    Route::post('/scanner/validate', [ScannerController::class, 'validateTicket']);
    Route::post('/scanner/redeem', [ScannerController::class, 'redeem']);

    Route::get('/meals/{meal}/summary', [ReportController::class, 'summary']);
    Route::get('/meals/{meal}/scan-logs', [ReportController::class, 'scanLogs']);

    Route::apiResource('events', EventController::class);

    Route::get('/events/meal-sessions/all', [MealSessionController::class, 'getMealSessions']);
    Route::get('/events/{event}/meal-sessions', [MealSessionController::class, 'index']);
    Route::post('/events/{event}/meal-sessions', [MealSessionController::class, 'store']);
    Route::get('/meal-sessions/{mealSession}', [MealSessionController::class, 'show']);
    Route::put('/meal-sessions/{mealSession}', [MealSessionController::class, 'update']);
    Route::patch('/meal-sessions/{mealSession}/status', [MealSessionController::class, 'updateStatus']);
    Route::delete('/meal-sessions/{mealSession}', [MealSessionController::class, 'destroy']);

    Route::get('/events/{event}/passes', [EventPassController::class, 'index']);
    Route::post('/events/{event}/generate-passes', [EventPassController::class, 'generate']);
    Route::get('/passes/{pass}', [EventPassController::class, 'show']);
    Route::patch('/passes/{pass}/void', [EventPassController::class, 'void']);
    Route::get('/passes/{pass}/qr', [EventPassController::class, 'qr']);
    Route::get('/passes/{pass}/qr/download', [EventPassController::class, 'downloadQr']);
    Route::patch('/events/{event}/status', [EventController::class, 'updateStatus']);

    Route::post('/scanner/redeem', [ScannerController::class, 'redeem']);
    Route::get('/events/{event}/passes/download-pdf', [EventPassController::class, 'downloadPdf']);
    
    Route::post('/events/{event}/attendance/scan', [DailyAttendanceController::class, 'scan']);
   Route::get('/events/{event}/attendance', [DailyAttendanceController::class, 'index']);
   Route::get('/events/{event}/attendance/summary', [DailyAttendanceController::class, 'summary']);
   Route::get('/attendance/config', [DailyAttendanceController::class, 'config']);

   Route::prefix('events/{event}/room-checkins')->group(function () {
    Route::post('/scan-lookup', [RoomCheckinController::class, 'scanLookup']);
    Route::post('/checkin', [RoomCheckinController::class, 'checkin']);
    Route::post('/reallocate', [RoomCheckinController::class, 'reallocate']);
});

       Route::get('/events/{event}/rooms', [RoomAllocationController::class, 'rooms']);

    Route::post('/events/{event}/room-allocations/check-in', [RoomAllocationController::class, 'checkIn']);
    Route::post('/events/{event}/room-allocations/reallocate', [RoomAllocationController::class, 'reallocate']);

    Route::get('/events/{event}/attendees/{attendee}/current-room', [RoomAllocationController::class, 'attendeeCurrentRoom']);
    Route::get('/events/{event}/attendees/{attendee}/room-allocation-history', [RoomAllocationController::class, 'attendeeAllocationHistory']);

    Route::post('search', [AttendeeRegistrationController::class, 'search']);
    Route::get('/events/{event}/registered-attendees', [AttendeeRegistrationController::class, 'registeredAttendees']);
    Route::get('/events/{event}/registered-attendees2', [AttendeeRegistrationController::class, 'registeredAttendees2']);

        Route::post('/events/{event}/room-checkins/checkin', [RoomCheckinController::class, 'checkin']);
        Route::post('/events/{event}/room-checkins/scan-checkin', [RoomCheckinController::class, 'scanCheckin']);


    Route::prefix('events')->group(function () {
    Route::post('/{event}/passes/verify', [AttendeeRegistrationController::class, 'verifyPass']);
    Route::post('{event}/registrations', [AttendeeRegistrationController::class, 'register']);
    });


            Route::get('/events/{event}/incidents', [IncidentController::class, 'index']);
    Route::post('/events/{event}/incidents', [IncidentController::class, 'store']);
    Route::get('/events/{event}/incidents/{incident}', [IncidentController::class, 'show']);

    Route::patch('/events/{event}/incidents/{incident}/status', [IncidentController::class, 'updateStatus']);
    Route::patch('/events/{event}/incidents/{incident}/assign', [IncidentController::class, 'assign']);
    Route::patch('/events/{event}/incidents/{incident}/resolve', [IncidentController::class, 'resolve']);
    Route::post('/events/{event}/incidents/{incident}/updates', [IncidentController::class, 'addUpdate']);

    Route::post('/events/{event}/incidents/{incident}/updates', [IncidentController::class, 'addUpdate']);

    Route::get('/events/{event}/attendees/{attendeeId}', [AttendeeRegistrationController::class, 'show']);
    Route::get('/dashboard/issam-central', [DashboardController::class, 'issamCentral']);



    // Route::get('/dashboard/issam-central', [IssamCentralDashboardController::class, 'index']);
    Route::get('/dashboard/issam-central/detail', [IssamCentralDashboardController::class, 'detail']);
    Route::get('dashboard/issam-central/attendance-trend', [IssamCentralDashboardController::class, 'attendanceTrend']);

       // ==========================================
    // FOOD SUPPLY MANAGEMENT (Admin/Food Committee)
    // ==========================================
    
    // Record new food supply from vendor
    Route::post('/food/supplies', [MealController::class, 'recordSupply']);
    
    // Top up existing food supply
    Route::post('/food/supplies/{supplyId}/topup', [MealController::class, 'topUpSupply']);
    
    // Get current food inventory (supports eventId or mealSessionId filter)
    Route::get('/food/inventory', [MealController::class, 'getInventory']);
    
    // Get recent food supplies
    Route::get('/food/supplies/recent', [MealController::class, 'getRecentSupplies']);

        // ==========================================
    // REPORTS (Admin/Food Committee)
    // ==========================================
    
    // Generate daily distribution and rating report
      Route::get('/food/reports/daily', [MealController::class, 'generateDailyReport']);




    // ==========================================
    // MEDICATION SUPPLY MANAGEMENT (Nurse/Medical Staff)
    // ==========================================
    
    // Record new medication supply
    Route::post('/medications/supplies', [MedicationController::class, 'recordSupply']);
    
    // Top up existing medication supply
    Route::post('/medications/supplies/{supplyId}/topup', [MedicationController::class, 'topUpSupply']);
    
    // Get current medication inventory
    Route::get('/medications/inventory', [MedicationController::class, 'getInventory']);
    
    // Get recent medication supplies
    Route::get('/medications/supplies/recent', [MedicationController::class, 'getRecentSupplies']);
    
    
    // ==========================================
    // MEDICATION DISPENSING (Nurse/Medical Staff)
    // ==========================================
    
    // Get available medications for dispensing dropdown
    Route::get('/medications/available', [MedicationController::class, 'getAvailableMedications']);
    
    // Search for attendees/participants by name or ID
    Route::get('/medications/attendees/search', [MedicationController::class, 'searchAttendees']);
    
    // Dispense medication to participant
    Route::post('/medications/dispense', [MedicationController::class, 'dispenseMedication']);
    
    // Get attendee's medication history
    Route::get('/medications/attendees/{attendeeId}/history', [MedicationController::class, 'getAttendeeHistory']);
    
    // Get all medication dispensing records
    Route::get('/medications/dispensing/all', [MedicationController::class, 'getAllDispensing']);
    
    // Get all recipients with medication history (paginated)
    Route::get('/medications/recipients', [MedicationController::class, 'getAllRecipients']);
    
    // Search medication history by recipient name (participants + non-participants)
    Route::get('/medications/history/search', [MedicationController::class, 'searchRecipientHistory']);
    
    

    Route::get('/medications/attendees/{attendeeId}/medical-info', [MedicationController::class, 'getAttendeeMedicalInfo']);
    
    // Get attendee medical information by QR code
    Route::get('/medications/qr/{qrCode}/medical-info', [MedicationController::class, 'getAttendeeMedicalInfoByQr']);
    
    // Update attendee medical information
    Route::put('/medications/attendees/{attendeeId}/medical-info', [MedicationController::class, 'updateAttendeeMedicalInfo']);
    

    // ==========================================
    // REPORTS (Medical Staff/Admin)
    // ==========================================
    
    // Generate medication inventory and dispensing report
    Route::get('/medications/reports', [MedicationController::class, 'generateReport']);


        // Scan QR code to get attendee info
    Route::post('/exits/scan', [ExitController::class, 'scanQRCode']);
    
    // Record exit
    Route::post('/exits/record-exit', [ExitController::class, 'recordExit']);
    
    // Record return
    Route::post('/exits/record-return', [ExitController::class, 'recordReturn']);
    
    // Get currently out participants
    Route::get('/exits/currently-out', [ExitController::class, 'getCurrentlyOut']);
    
    // Get exit history
    Route::get('/exits/history', [ExitController::class, 'getExitHistory']);
    
    // Get statistics
    Route::get('/exits/statistics', [ExitController::class, 'getStatistics']);


    // Medical Information Routes
Route::post('/participant/medical-info', [ParticipantMedicalInfoController::class, 'store']);
Route::get('/participant/medical-info/{attendeeId}', [ParticipantMedicalInfoController::class, 'show']);

    // Color Groups Dashboard
  
        Route::get('colors', [ColorGroupsController::class, 'index']);
        
        // Get Sub-CLs for a specific color
        Route::get('colors/{colorId}/subcls', [ColorGroupsController::class, 'getSubCLs']);
        
        // Get participants for a specific color (with optional Sub-CL filter)
        Route::get('colors/{colorId}/participants', [ColorGroupsController::class, 'getParticipants']);
    

        Route::get('/meals/ratings/statistics', [MealRatingStatisticsController::class, 'statistics']);
    Route::get('/meals/ratings/statistics/{mealSession}', [MealRatingStatisticsController::class, 'show']);
    Route::get('/meals/ratings/overall', [MealRatingStatisticsController::class, 'overall']);
    Route::get('/meals/ratings/export', [MealRatingStatisticsController::class, 'export']);


    Route::get('/incidents', [IncidentController::class, 'index']);
    Route::post('/incidents', [IncidentController::class, 'store']);
    Route::get('/incidents/{incident}', [IncidentController::class, 'show']);
    Route::patch('/incidents/{incident}/status', [IncidentController::class, 'updateStatus']);
    Route::patch('/incidents/{incident}/assign', [IncidentController::class, 'assign']);
    Route::patch('/incidents/{incident}/resolve', [IncidentController::class, 'resolve']);
    Route::post('/incidents/{incident}/updates', [IncidentController::class, 'addUpdate']);
    
    // Attendee routes - returns attendees from active event
    Route::get('/attendees', [AttendeeController::class, 'index']);
    
    // Room routes - returns rooms from active event
    Route::get('/rooms', [RoomController::class, 'index']);

});

// Route::get('/staff', [StaffController::class, 'index']);
Route::get('/feedback/download-pdf', [FeedbackController::class, 'downloadPdf']);

    Route::get('/staff', [StaffController::class, 'index']);
    // Route::post('/feedback', [FeedbackController::class, 'store']);
    Route::get('/feedback', [FeedbackController::class, 'summary']);


    // ==========================================
    // MEAL RATINGS (Participants)
    // ==========================================
    
    // Get meal sessions available for rating by current user
    Route::get('/meals/sessions/rateable', [MealController::class, 'getRateableMeals']);
    
    // Submit a rating for a meal session
    Route::post('/meals/ratings', [MealController::class, 'submitRating']);
    
    // Get current user's rating history
    Route::get('/meals/ratings/my-ratings', [MealController::class, 'getMyRatings']);
    
    
