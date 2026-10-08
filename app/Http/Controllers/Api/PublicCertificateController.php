<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Services\CertificateService;
use App\Services\CertificateDownloadLogger;
use App\Services\EligibilityService;
use App\Services\PresenterService;
use App\Services\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * PUBLIC certificate flow (no login) for attendees and authors:
 *
 *   1. POST /public/certificates/verify         email + phone used at registration
 *        - no match            -> 404 "couldn't find a registration"
 *        - not accredited      -> 403 code "not_accredited"  (ineligible)
 *        - accredited          -> a short-lived session token
 *   2. GET/POST /public/certificates/questionnaire   the feedback questionnaire
 *   3. GET /public/certificates                       what can be downloaded
 *      GET /public/certificates/{type}                the PDF
 *
 * After step 1 every call carries the token in the X-Certificate-Token header.
 * Failures answer 403/404 (never 401) so the front end does not treat them as an expired login.
 */
class PublicCertificateController extends Controller
{
    private const SESSION_MINUTES = 180;

    public function __construct(
        protected QuestionnaireService $questionnaire,
        protected CertificateService $certificates,
        protected PresenterService $presenters,
        protected EligibilityService $eligibility,
        protected CertificateDownloadLogger $downloadLogger,

    ) {}

    // ── Step 1: verify ───────────────────────────────────────────────────

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $event = Event::where('status', 'active')->first();
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
        }

        $email = strtolower(trim($data['email']));

        $attendee = Attendee::where('eventId', $event->eventId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get()
            ->first(fn (Attendee $a) => $this->phonesMatch((string) $a->phoneNumber, $data['phone']));

        if (!$attendee) {
            return response()->json([
                'success' => false,
                'code'    => 'not_found',
                'message' => "We couldn't find a registration with that email and phone number. Use the same details you registered with.",
            ], 404);
        }

        if (!$attendee->isAccredited && !$attendee->manualOverride) {
            return response()->json([
                'success' => false,
                'code'    => 'not_accredited',
                'message' => 'Our records show you were not accredited at the event, so you are not eligible for a certificate. If you think this is a mistake, please contact the organisers.',
            ], 403);
        }

        $eligible = $this->eligibility->recalculate($attendee);

        $token = Str::random(64);
        Cache::put($this->cacheKey($token), $attendee->attendeeId, now()->addMinutes(self::SESSION_MINUTES));

        return response()->json([
            'success' => true,
            'message' => 'Verified.',
            'data'    => [
                'token'    => $token,
                'name'     => $this->certificates->fullName($attendee),
                'eligible' => $eligible,
                // already answered the questionnaire before -> straight to the downloads
                'next'     => $eligible ? 'download' : 'questionnaire',
            ],
        ]);
    }

    // ── Step 2: questionnaire ────────────────────────────────────────────

    public function questionnaire(Request $request): JsonResponse
    {
        [$event, $attendee] = $this->resolve($request);

        if (!$attendee) {
            return $this->sessionExpired();
        }

        return response()->json([
            'success' => true,
            'message' => 'Questionnaire retrieved.',
            'data'    => $this->questionnaire->payload($event, $attendee),
        ]);
    }

    public function submitQuestionnaire(Request $request): JsonResponse
    {
        [$event, $attendee] = $this->resolve($request);

        if (!$attendee) {
            return $this->sessionExpired();
        }

        [$status, $body] = $this->questionnaire->submit($event, $attendee, $request->input('answers', []));

        if ($status < 300) {
            $this->eligibility->recalculate($attendee);   // feedback now exists -> eligible
        }

        return response()->json($body, $status);
    }

    // ── Step 3: certificates ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        [$event, $attendee] = $this->resolve($request);

        if (!$attendee) {
            return $this->sessionExpired();
        }

        $eligible = $this->eligibility->recalculate($attendee);

        $list = [[
            'type'      => 'attendance',
            'label'     => CertificateService::label('attendance'),
            'available' => $eligible,
        ]];

        foreach ($this->presenters->presentationTypesForEmail((string) $attendee->email) as $type) {
            $list[] = [
                'type'      => $type,
                'label'     => CertificateService::label($type),
                'available' => $eligible,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Certificates retrieved.',
            'data'    => [
                'name'         => $this->certificates->fullName($attendee),
                'eligible'     => $eligible,
                'certificates' => $list,
            ],
        ]);
    }

    // public function download(Request $request, string $type)
    // {
    //     if (!in_array($type, CertificateService::typeKeys(), true)) {
    //         return response()->json(['success' => false, 'message' => 'Unknown certificate type.'], 404);
    //     }

    //     [$event, $attendee] = $this->resolve($request);

    //     if (!$attendee) {
    //         return $this->sessionExpired();
    //     }

    //     if (!$this->eligibility->recalculate($attendee)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Please complete the feedback questionnaire first. Your certificates unlock right after.',
    //         ], 403);
    //     }

    //     if ($type !== 'attendance'
    //         && !in_array($type, $this->presenters->presentationTypesForEmail((string) $attendee->email), true)) {
    //         return response()->json(['success' => false, 'message' => 'You are not listed as a presenter for this certificate.'], 403);
    //     }

    //     $certificate = $this->certificates->issue($attendee, $event, $type);
    //     $pdf         = $this->certificates->generate($attendee, $certificate, $event);

    //     $id = $attendee->uniqueId ?: $attendee->attendeeId;

    //     return response($pdf, 200)
    //         ->header('Content-Type', 'application/pdf')
    //         ->header('Content-Disposition', 'attachment; filename="' . $id . '-' . $type . '-certificate.pdf"');
    // }

    public function download(Request $request, string $type)
{
    if (!in_array($type, CertificateService::typeKeys(), true)) {
        return response()->json([
            'success' => false,
            'message' => 'Unknown certificate type.',
        ], 404);
    }

    [$event, $attendee] = $this->resolve($request);

    if (!$attendee) {
        return $this->sessionExpired();
    }

    if (!$this->eligibility->recalculate($attendee)) {
        return response()->json([
            'success' => false,
            'message' => 'Please complete the feedback questionnaire first. Your certificates unlock right after.',
        ], 403);
    }

    if (
        $type !== CertificateService::TYPE_ATTENDANCE
        && !in_array(
            $type,
            $this->presenters->presentationTypesForEmail(
                (string) $attendee->email
            ),
            true
        )
    ) {
        return response()->json([
            'success' => false,
            'message' => 'You are not listed as a presenter for this certificate.',
        ], 403);
    }

    /*
     * Generate the certificate.
     */
    $certificate = $this->certificates->issue(
        $attendee,
        $event,
        $type
    );

    $pdf = $this->certificates->generate(
        $attendee,
        $certificate,
        $event
    );

    /*
     * Record successful download.
     *
     * This happens AFTER PDF generation, so we don't count
     * failed certificate generations as downloads.
     */
    $this->downloadLogger->log(
        $attendee,
        $event,
        $type,
        'public',
        $request
    );

    $id = $attendee->uniqueId ?: $attendee->attendeeId;

    return response($pdf, 200)
        ->header('Content-Type', 'application/pdf')
        ->header(
            'Content-Disposition',
            'attachment; filename="' .
            $id .
            '-' .
            $type .
            '-certificate.pdf"'
        );
}

    // ── helpers ──────────────────────────────────────────────────────────

    private function cacheKey(string $token): string
    {
        return 'public-certificate-session:' . hash('sha256', $token);
    }

    /** @return array{0: ?Event, 1: ?Attendee} */
    private function resolve(Request $request): array
    {
        $token = (string) ($request->header('X-Certificate-Token') ?: $request->query('token', ''));
        $attendeeId = $token !== '' ? Cache::get($this->cacheKey($token)) : null;

        $event = Event::where('status', 'active')->first();

        $attendee = ($attendeeId && $event)
            ? Attendee::where('attendeeId', $attendeeId)->where('eventId', $event->eventId)->first()
            : null;

        return [$event, $attendee];
    }

    private function sessionExpired(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code'    => 'session_expired',
            'message' => 'Your session has expired. Please enter your email and phone number again.',
        ], 403);
    }

    /** Compare phone numbers by their last 10 digits, so 0803..., +234803... and 234 803... all match. */
    private function phonesMatch(string $stored, string $entered): bool
    {
        $a = substr(preg_replace('/\D+/', '', $stored), -10);
        $b = substr(preg_replace('/\D+/', '', $entered), -10);

        return strlen($a) >= 7 && $a === $b;
    }
}