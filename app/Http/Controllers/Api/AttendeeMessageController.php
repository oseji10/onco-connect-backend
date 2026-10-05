<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Notifications\AttendeeCustomNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class AttendeeMessageController extends Controller
{
    /** Keep in sync with AttendeeController::store() */
    private const CATEGORIES = [
        'healthcare_professional',
        'cancer_advocate',
        'cancer_survivor',
        'development_partner',
        'student',
        'researcher',
        'general_public',
        'government_official',
        'other',
        'radiographer',
        'nurse',
        'doctor',
        'pharmacist',
        'lab_scientist',
        'medical_physicist',
        'other_health_worker',
    ];

    /**
     * POST /api/conference/notifications/custom   (multipart/form-data)
     *
     * Send the same subject/message (+ optional attachments) to registered
     * participants of the active event. Exactly one of `category` /
     * `attendeeIds` must be supplied. `participationType` optionally narrows
     * a category send to Physical or Virtual.
     *
     * Body:
     *   { category: <category>|'all', participationType?: 'Physical'|'Virtual', subject, message, attachments?: File[] }
     *   OR
     *   { attendeeIds: number[], subject, message, attachments?: File[] }
     */
    public function sendCustom(Request $request): JsonResponse
    {
        $activeEvent = Event::where('status', 'active')->first();

        if (! $activeEvent) {
            return response()->json([
                'success' => false,
                'message' => 'No active event found.',
            ], 404);
        }

        $validated = $request->validate([
            'category' => ['nullable', Rule::in([...self::CATEGORIES, 'all'])],
            'participationType' => ['nullable', Rule::in(['Physical', 'Virtual'])],
            'attendeeIds' => ['nullable', 'array', 'min:1'],
            'attendeeIds.*' => ['integer'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:10240', // KB
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,zip',
            ],
        ]);

        $hasCategory = ! empty($validated['category']);
        $hasIds = ! empty($validated['attendeeIds']);

        if ($hasCategory === $hasIds) {
            return response()->json([
                'success' => false,
                'message' => 'Provide exactly one of "category" or "attendeeIds".',
            ], 422);
        }

        $query = Attendee::query()->where('eventId', $activeEvent->eventId);

        if ($hasIds) {
            $query->whereIn('attendeeId', $validated['attendeeIds']);
        } else {
            if ($validated['category'] !== 'all') {
                $query->where('category', $validated['category']);
            }
            if (! empty($validated['participationType'])) {
                $query->where('participationType', $validated['participationType']);
            }
        }

        $attendees = $query->get();

        if ($attendees->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No matching participants to send to.',
            ], 422);
        }

        // Skip people with no email, and send once per email address
        // (the same person may have registered more than once).
        $withEmail = $attendees->filter(fn ($a) => filled($a->email));
        $skippedNoEmail = $attendees->count() - $withEmail->count();

        $recipients = $withEmail->unique(fn ($a) => strtolower(trim($a->email)))->values();
        $duplicatesMerged = $withEmail->count() - $recipients->count();

        if ($recipients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'None of the matching participants have an email address on file.',
            ], 422);
        }

        // Persist uploads first: temp files vanish when the request ends,
        // which would break queued mail.
        $attachments = [];
        foreach ($request->file('attachments', []) as $file) {
            $attachments[] = [
                'path' => $file->store('mail-attachments', 'local'),
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
            ];
        }

        foreach ($recipients as $attendee) {
            $name = trim(($attendee->title ? $attendee->title . ' ' : '')
                . $attendee->firstName . ' ' . $attendee->lastName);

            Notification::route('mail', [trim($attendee->email) => $name])->notify(
                new AttendeeCustomNotification(
                    $validated['subject'],
                    $validated['message'],
                    $name,
                    $attachments
                )
            );
        }

        $notes = '';
        if ($skippedNoEmail > 0) {
            $notes .= ", {$skippedNoEmail} skipped (no email on file)";
        }
        if ($duplicatesMerged > 0) {
            $notes .= ", {$duplicatesMerged} duplicate registration(s) merged";
        }

        return response()->json([
            'success' => true,
            'message' => "Sent to {$recipients->count()} recipient(s){$notes}.",
            'data' => [
                'sent' => $recipients->count(),
                'skipped_no_email' => $skippedNoEmail,
                'duplicates_merged' => $duplicatesMerged,
            ],
        ]);
    }
}