<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Role;
use App\Models\User;
use App\Models\Event;
use App\Models\EventPass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Mail\AttendeePassMail;
use App\Services\EventPassGeneratorService;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Mail;
use App\Services\PassPdfService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class AttendeeController extends Controller
{

public function __construct(
    protected EventPassGeneratorService $passGenerator,
    protected QrCodeService $qrCodeService,
    protected PassPdfService $passPdfService,
) {}

    public function index(Request $request): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (!$activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $attendees = Attendee::where('eventId', $activeEvent->eventId)
            ->select([
                'attendeeId',
                'firstName',
                'lastName',
                'uniqueId',
                'phoneNumber',
                'maritalStatus',
                'gender',
                'email',
                'organizationName',
                'participationType',
                'category',
                'stateOfResidence',
                'title',
                'photoUrl',

            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($attendee) {
                return [
                    'attendeeId' => $attendee->attendeeId,
                    'title' => $attendee->title,
                    'firstName' => $attendee->firstName,
                    'lastName' => $attendee->lastName,
                    'fullName' => trim(($attendee->firstName ?? '') . ' ' . ($attendee->lastName ?? '') . ' ' . ($attendee->otherNames ?? '')),
                    'uniqueId' => $attendee->uniqueId,
                    'phoneNumber' => $attendee->phoneNumber,
                    'gender' => $attendee->gender,
                    'email' => $attendee->email,
                    'maritalStatus' => $attendee->maritalStatus,
                    'participationType' => $attendee->participationType,
                    'category' => $attendee->category,
                    'organizationName' => $attendee->organizationName,
                    'stateOfResidence' => $attendee->stateOfResidence,
                    'photoUrl' => $attendee->photoUrl,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Attendees retrieved successfully.',
            'data' => $attendees,
        ]);
    }



// public function store(Request $request): JsonResponse
// {
//     $activeEvent = Event::where('status', 'active')->first();

//     if (!$activeEvent) {
//         return response()->json([
//             'success' => false,
//             'message' => 'No active event found.',
//         ], 404);
//     }

//     $validated = $request->validate([
//         'title' => ['required', 'string', 'max:255'],
//         'firstName' => ['required', 'string'],
//         'lastName' => ['required', 'string'],
//         'otherNames' => ['nullable', 'string'],
//         'email' => ['nullable', 'string'],
//         'phoneNumber' => ['nullable', 'string'],
//         'gender' => ['nullable', 'string'],
//         'maritalStatus' => ['nullable', 'string'],
//         'organizationName' => ['nullable', 'string'],
//         'stateOfResidence' => ['nullable', 'string'],
//         'category' => [
//             'required',
//             Rule::in(['healthcare_professional', 'cancer_survivor', 'development_partner', 'student', 'researcher', 'general_public', 'government_official', 'other']),
//         ],
//         'participationType' => [
//             'required',
//             Rule::in(['Physical', 'Virtual']),
//         ],
//         'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'], // Max 2MB
//     ]);

//     return DB::transaction(function () use ($validated, $activeEvent, $request) {
//     $attendeeData = [
//         'eventId' => $activeEvent->eventId,
//         'title' => trim($validated['title']),
//         'category' => trim($validated['category']),
//         'firstName' => $validated['firstName'],
//         'lastName' => $validated['lastName'],
//         'otherNames' => $validated['otherNames'] ?? null,
//         'phoneNumber' => $validated['phoneNumber'] ?? null,
//         'email' => $validated['email'] ?? null,
//         'gender' => $validated['gender'] ?? null,
//         'maritalStatus' => $validated['maritalStatus'] ?? null,
//         'participationType' => $validated['participationType'],
//         'organizationName' => $validated['organizationName'] ?? null,
//         'stateOfResidence' => $validated['stateOfResidence'] ?? null,
//         'uniqueId' => $this->generateUniqueId(),
//         'registeredBy' => Auth::id(),
//     ];

//     if ($request->hasFile('photo')) {
//         $photo = $request->file('photo');
//         $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
//         $path = $photo->storeAs('attendee_photos', $filename, 'public');

//         $attendeeData['photoUrl'] = $path;
//     }

//     $attendee = Attendee::create($attendeeData);

//     return response()->json([
//         'success' => true,
//         'message' => 'Attendee registered successfully.',
//         'data' => $attendee,
//     ], 201);
// });
// }




public function store(Request $request): JsonResponse
{
    $activeEvent = Event::where('status', 'active')->first();

    if (!$activeEvent) {
        return response()->json([
            'success' => false,
            'message' => 'No active event found.',
        ], 404);
    }

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'firstName' => ['required', 'string'],
        'lastName' => ['required', 'string'],
        'otherNames' => ['nullable', 'string'],
        'email' => ['nullable', 'string'],
        'phoneNumber' => ['nullable', 'string'],
        'gender' => ['nullable', 'string'],
        'maritalStatus' => ['nullable', 'string'],
        'organizationName' => ['nullable', 'string'],
        'stateOfResidence' => ['nullable', 'string'],
        'category' => [
            'required',
            Rule::in(['healthcare_professional', 'cancer_survivor', 'development_partner', 'student', 'researcher', 'general_public', 'government_official', 'other']),
        ],
        'participationType' => [
            'required',
            Rule::in(['Physical', 'Virtual']),
        ],
        'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'], // Max 2MB
    ]);

    return DB::transaction(function () use ($validated, $activeEvent, $request) {
        $attendeeData = [
           'eventId' => $activeEvent->eventId,
        'title' => trim($validated['title']),
        'category' => trim($validated['category']),
        'firstName' => $validated['firstName'],
        'lastName' => $validated['lastName'],
        'otherNames' => $validated['otherNames'] ?? null,
        'phoneNumber' => $validated['phoneNumber'] ?? null,
        'email' => $validated['email'] ?? null,
        'gender' => $validated['gender'] ?? null,
        'maritalStatus' => $validated['maritalStatus'] ?? null,
        'participationType' => $validated['participationType'],
        'organizationName' => $validated['organizationName'] ?? null,
        'stateOfResidence' => $validated['stateOfResidence'] ?? null,
        'uniqueId' => $this->generateUniqueId(),
        'registeredBy' => Auth::id(),
        ];

        if ($request->hasFile('photo')) {
            $photo    = $request->file('photo');
            $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $path     = $photo->storeAs('attendee_photos', $filename, 'public');
            $attendeeData['photoUrl'] = $path;
        }

        $attendee = Attendee::create($attendeeData);

    // ── Create User account ────────────────────────────────────────────
    $plainPassword = null;
    $user          = null;

    if (!empty($attendee->email)) {
        $participantRole = Role::where('roleName', 'participant')->first();

        if (!$participantRole) {
            throw new \RuntimeException(
                'Participant role not found. Please seed the roles table.'
            );
        }

        // Guard against duplicate email (re-registration edge case)
        $existingUser = User::where('email', $attendee->email)->first();

        if (!$existingUser) {
            $plainPassword = Str::random(10);

            $user = User::create([
                'facilityId'  => null,
                'firstName'   => $attendee->firstName,
                'lastName'    => $attendee->lastName,
                'email'       => $attendee->email,
                'phoneNumber' => $attendee->phoneNumber,
                'password'    => Hash::make($plainPassword),
                'role'        => $participantRole->roleId,
                'status'      => 'active',
            ]);
        } else {
            $user = $existingUser;
        }

        // Link user back to attendee
        $attendee->update(['userId' => $user->id]);
    }

    // ── Generate pass ──────────────────────────────────────────────────
    $pass = EventPass::create([
        'eventId'      => $activeEvent->eventId,
        'attendeeId'   => $attendee->getKey(),
        'passCode'     => bin2hex(random_bytes(16)),
        'serialNumber' => $this->generateSerialNumber($activeEvent),
        'status'       => 'active',
    ]);

    $this->qrCodeService->generateForEventPass($pass);
    $pass = $pass->fresh();

    $pdfContent = $this->passPdfService->generate($attendee, $pass);

    if (!empty($attendee->email)) {
        Mail::to($attendee->email)
            ->send(new AttendeePassMail($attendee, $pass, $pdfContent, $plainPassword));
    }

    return response()->json([
        'success' => true,
        'message' => 'Attendee registered successfully.' .
                     (empty($attendee->email) ? '' : ' Pass and login credentials sent to email.'),
        'data'    => $attendee->load(['pass', 'user']),
    ], 201);
});

}



public function resendPass(Attendee $attendee): JsonResponse
{
    if (empty($attendee->email)) {
        return response()->json([
            'success' => false,
            'message' => 'This participant has no email address on record.',
        ], 422);
    }

    $pass = $attendee->pass; // assumes HasOne relationship

    if (!$pass) {
        return response()->json([
            'success' => false,
            'message' => 'No pass found for this participant.',
        ], 404);
    }

    // Regenerate QR (in case it was lost too)
    $this->qrCodeService->generateForEventPass($pass);
    $pass->refresh();

    // Regenerate PDF in memory and send
    $pdfContent = $this->passPdfService->generate($attendee, $pass);

    Mail::to($attendee->email)
        ->send(new AttendeePassMail($attendee, $pass, $pdfContent));

    return response()->json([
        'success' => true,
        'message' => 'Pass resent successfully to ' . $attendee->email,
    ]);
}

private function generateSerialNumber(Event $event): string
{
    $count = $event->passes()->count();
    return strtoupper('ICW-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT));
}

public function update(Request $request): JsonResponse
{
    $activeEvent = Event::where('status', 'active')->first();

    if (!$activeEvent) {
        return response()->json([
            'success' => false,
            'message' => 'No active event found.',
        ], 404);
    }

    $validated = $request->validate([
        'title' => ['required', 'string', 'max:255'],
        'firstName' => ['required', 'string'],
        'lastName' => ['required', 'string'],
        'otherNames' => ['nullable', 'string'],
        'email' => ['nullable', 'string'],
        'phoneNumber' => ['nullable', 'string'],
        'gender' => ['nullable', 'string'],
        'maritalStatus' => ['nullable', 'string'],
        'organizationName' => ['nullable', 'string'],
        'stateOfResidence' => ['nullable', 'string'],
        'category' => [
            'required',
            Rule::in([
                'healthcare_professional',
                'cancer_survivor',
                'development_partner',
                'student',
                'researcher',
                'general_public',
                'government_official',
                'other'
            ]),
        ],
        'participationType' => [
            'required',
            Rule::in(['Physical', 'Virtual']),
        ],
        'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'], // Max 2MB
    ]);

    $attendee = Attendee::where('attendeeId', $request->attendeeId)->first();

    if (!$attendee) {
        return response()->json([
            'success' => false,
            'message' => 'Attendee not found.',
        ], 404);
    }

    return DB::transaction(function () use ($validated, $attendee, $request) {
        $updateData = $validated;
        unset($updateData['attendeeId']);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($attendee->photo && Storage::disk('public')->exists($attendee->photo)) {
                Storage::disk('public')->delete($attendee->photo);
            }

            $photo = $request->file('photo');
            $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $path = $photo->storeAs('attendee_photos', $filename, 'public');
            $updateData['photoUrl'] = $path;
        }

        $attendee->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Registration updated successfully.',
            'data' => $attendee->fresh(),
        ]);
    });
}

// Add this method to handle photo deletion if needed
public function deletePhoto(Request $request): JsonResponse
{
    $request->validate([
        'attendeeId' => ['required', 'exists:attendees,attendeeId'],
    ]);

    $attendee = Attendee::where('attendeeId', $request->attendeeId)->first();

    if (!$attendee) {
        return response()->json([
            'success' => false,
            'message' => 'Attendee not found.',
        ], 404);
    }

    return DB::transaction(function () use ($attendee) {
        if ($attendee->photo && Storage::disk('public')->exists($attendee->photo)) {
            Storage::disk('public')->delete($attendee->photo);
        }

        $attendee->update(['photo' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Photo deleted successfully.',
            'data' => $attendee->fresh(),
        ]);
    });
}

protected function generateUniqueId(): string
{
    $datePart = now()->format('Ymd');
    $countToday = Attendee::whereDate('created_at', now()->toDateString())->count() + 1;
    return 'ICW-' . $datePart . '-' . str_pad((string) $countToday, 4, '0', STR_PAD_LEFT);
}
}