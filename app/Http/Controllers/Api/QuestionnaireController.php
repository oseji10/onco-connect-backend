<?php
// REPLACES the earlier logged-in QuestionnaireController. Now a thin wrapper over the service.

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Models\Event;
use App\Services\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionnaireController extends Controller
{
    public function __construct(protected QuestionnaireService $service) {}

    private function resolve(): array
    {
        $event = Event::where('status', 'active')->first();
        $attendee = $event
            ? Attendee::where('userId', Auth::id())->where('eventId', $event->eventId)->first()
            : null;

        return [$event, $attendee];
    }

    public function show(): JsonResponse
    {
        [$event, $attendee] = $this->resolve();

        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'No registration found for the active event.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Questionnaire retrieved.',
            'data'    => $this->service->payload($event, $attendee),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        [$event, $attendee] = $this->resolve();

        if (!$attendee) {
            return response()->json(['success' => false, 'message' => 'No registration found.'], 404);
        }

        [$status, $body] = $this->service->submit($event, $attendee, $request->input('answers', []));

        return response()->json($body, $status);
    }
}
