<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpeakerRequest;
use App\Http\Requests\UpdateSpeakerStatusRequest;
use App\Http\Resources\SpeakerResource;
use App\Models\Speaker;
use App\Notifications\SpeakerDecisionNotification;
use App\Notifications\SpeakerSubmittedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SpeakerController extends Controller
{
    /**
     * POST /api/speakers/register
     * Public — no auth required.
     */
    public function store(StoreSpeakerRequest $request): JsonResponse
    {
        $photoPath = $request->file('photo')->store('speakers/photos', 'public');
        $cvPath = $request->file('cv')->store('speakers/cvs', 'public');

        $speaker = DB::transaction(function () use ($request, $photoPath, $cvPath) {
            $speaker = Speaker::create([
                'reference' => 'PENDING', // placeholder, replaced below once we have an id
                'session_type' => $request->input('sessionType'),
                'sub_theme' => $request->input('subTheme'),
                'session_title' => $request->input('sessionTitle'),
                'session_description' => $request->input('sessionDescription'),
                'participation_type' => $request->input('participationType'),

                'title' => $request->input('title'),
                'first_name' => $request->input('firstName'),
                'last_name' => $request->input('lastName'),
                'other_names' => $request->input('otherNames'),
                'organization' => $request->input('organization'),
                'job_title' => $request->input('jobTitle'),
                'bio' => $request->input('bio'),
                'physically_challenged' => filter_var(
                    $request->input('physicallyChallenged', false),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'accessibility_needs' => $request->input('accessibilityNeeds'),

                'email' => $request->input('email'),
                'country' => $request->input('country'),
                'state' => $request->input('state'),
                'phone_country_code' => $request->input('phoneCountryCode'),
                'phone_number' => $request->input('phoneNumber'),
                'linkedin_url' => $request->input('linkedinUrl'),
                'twitter_handle' => $request->input('twitterHandle'),

                'photo_path' => $photoPath,
                'cv_path' => $cvPath,

                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            $speaker->update([
                'reference' => sprintf('ICW2026-SPK-%04d', $speaker->id),
            ]);

            return $speaker;
        });

        $this->notifySpeakerOfSubmission($speaker);

        return (new SpeakerResource($speaker))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/speakers
     * Admin — list with search/filter/pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Speaker::query()->latest('submitted_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($sessionType = $request->query('sessionType')) {
            $query->where('session_type', $sessionType);
        }

        if ($subTheme = $request->query('subTheme')) {
            $query->where('sub_theme', $subTheme);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('organization', 'like', "%{$search}%")
                    ->orWhere('session_title', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('perPage', 10);
        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Speakers retrieved.',
            'data' => [
                'items' => SpeakerResource::collection($page->items()),
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'limit' => $page->perPage(),
                'totalPages' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/speakers/{speaker}
     * Admin — full detail.
     */
    public function show(Speaker $speaker): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Speaker retrieved.',
            'data' => new SpeakerResource($speaker),
        ]);
    }

    /**
     * PATCH /api/speakers/{speaker}/status
     * Admin — confirm/reject decision.
     */
    public function updateStatus(UpdateSpeakerStatusRequest $request, Speaker $speaker): JsonResponse
    {
        $status = $request->input('status');
        $speaker->update(['status' => $status]);

        $this->notifySpeakerOfDecision($speaker);

        return response()->json([
            'success' => true,
            'message' => "Speaker {$status}.",
            'data' => new SpeakerResource($speaker->fresh()),
        ]);
    }

    private function notifySpeakerOfSubmission(Speaker $speaker): void
    {
        if (! $speaker->email) {
            return;
        }

        Notification::route('mail', $speaker->email)
            ->notify(new SpeakerSubmittedNotification($speaker));
    }

    private function notifySpeakerOfDecision(Speaker $speaker): void
    {
        if (! $speaker->email) {
            return;
        }

        Notification::route('mail', $speaker->email)
            ->notify(new SpeakerDecisionNotification($speaker));
    }
}