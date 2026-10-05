<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Services\CertificateService;
use App\Services\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NO LOGIN. The attendee's personal token in the URL is the credential.
 * Invalid tokens always get the same generic 404 so nothing can be probed.
 */
class PublicQuestionnaireController extends Controller
{
    public function __construct(
        protected QuestionnaireService $service,
        protected CertificateService $certificates,
    ) {}

    private function resolve(string $token): array
    {
        $attendee = strlen($token) >= 32
            ? Attendee::where('questionnaireToken', $token)->first()
            : null;

        $event = $attendee ? Event::where('eventId', $attendee->eventId)->first() : null;

        return [$event, $attendee];
    }

    private function invalid(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'This link is not valid.'], 404);
    }

    public function show(string $token): JsonResponse
    {
        [$event, $attendee] = $this->resolve($token);
        if (!$attendee || !$event) return $this->invalid();

        return response()->json([
            'success' => true,
            'message' => 'Questionnaire retrieved.',
            'data'    => $this->service->payload($event, $attendee),
        ]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        [$event, $attendee] = $this->resolve($token);
        if (!$attendee || !$event) return $this->invalid();

        [$status, $body] = $this->service->submit($event, $attendee, $request->input('answers', []));

        return response()->json($body, $status);
    }

    public function certificate(string $token)
    {
        [$event, $attendee] = $this->resolve($token);
        if (!$attendee || !$event) return $this->invalid();

        return $this->certificates->download($attendee, $event);
    }
}
