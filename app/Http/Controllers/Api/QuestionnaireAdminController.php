<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin side: build the questionnaire, open/close it, read the results. Protect with your admin middleware. */
class QuestionnaireAdminController extends Controller
{
    private function event(): ?Event
    {
        return Event::where('status', 'active')->first();
    }

    private function noEvent(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'No active event found.'], 404);
    }

    private function format(QuestionnaireQuestion $q): array
    {
        return [
            'questionId' => $q->questionId,
            'type'       => $q->type,
            'prompt'     => $q->prompt,
            'options'    => $q->options,
            'required'   => (bool) $q->required,
        ];
    }

    public function index(): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'status'        => $event->questionnaireStatus ?? 'closed',
                'responseCount' => QuestionnaireResponse::where('eventId', $event->eventId)->count(),
                'questions'     => QuestionnaireQuestion::where('eventId', $event->eventId)
                    ->orderBy('sortOrder')->orderBy('questionId')->get()
                    ->map(fn ($q) => $this->format($q))->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        [$data, $error] = $this->validated($request);
        if ($error) return $error;

        $q = QuestionnaireQuestion::create($data + [
            'eventId'   => $event->eventId,
            'sortOrder' => (int) QuestionnaireQuestion::where('eventId', $event->eventId)->max('sortOrder') + 1,
        ]);

        return response()->json(['success' => true, 'message' => 'Question added.', 'data' => $this->format($q)], 201);
    }

    public function update(Request $request, QuestionnaireQuestion $question): JsonResponse
    {
        [$data, $error] = $this->validated($request);
        if ($error) return $error;

        $question->update($data);

        return response()->json(['success' => true, 'message' => 'Question updated.', 'data' => $this->format($question)]);
    }

    public function destroy(QuestionnaireQuestion $question): JsonResponse
    {
        $question->delete();

        return response()->json(['success' => true, 'message' => 'Question deleted.']);
    }

    /** POST { ids: [3,1,2] } : new order */
    public function reorder(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        foreach ($ids as $i => $id) {
            QuestionnaireQuestion::where('eventId', $event->eventId)->where('questionId', $id)->update(['sortOrder' => $i]);
        }

        return response()->json(['success' => true, 'message' => 'Order saved.']);
    }

    public function setStatus(Request $request): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $status = $request->validate(['status' => ['required', 'in:open,closed']])['status'];

        if ($status === 'open' && !QuestionnaireQuestion::where('eventId', $event->eventId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Add at least one question before opening the questionnaire.'], 422);
        }

        Event::where('eventId', $event->eventId)->update(['questionnaireStatus' => $status]);

        return response()->json([
            'success' => true,
            'message' => $status === 'open' ? 'Questionnaire is now open.' : 'Questionnaire closed.',
        ]);
    }

    /** One-click starter set, only when there are no questions yet. */
    public function seedDefaults(): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        if (QuestionnaireQuestion::where('eventId', $event->eventId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Questions already exist.'], 422);
        }

        $defaults = [
            ['rating', 'Overall, how would you rate the conference?', null, true],
            ['rating', 'How relevant was the content to your work or interests?', null, true],
            ['rating', 'How would you rate the organisation (registration, venue or platform, timekeeping)?', null, true],
            ['single_choice', 'How did you mainly attend?', ['In person', 'Online (virtual)', 'Both'], true],
            ['single_choice', 'Would you attend a future edition?', ['Yes', 'Maybe', 'No'], true],
            ['text', 'What was the most valuable thing you learned?', null, true],
            ['text', 'How can we improve next time?', null, false],
        ];

        foreach ($defaults as $i => [$type, $prompt, $options, $required]) {
            QuestionnaireQuestion::create([
                'eventId' => $event->eventId, 'type' => $type, 'prompt' => $prompt,
                'options' => $options, 'required' => $required, 'sortOrder' => $i,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Starter questions added.']);
    }

    public function results(): JsonResponse
    {
        $event = $this->event();
        if (!$event) return $this->noEvent();

        $responses = QuestionnaireResponse::where('eventId', $event->eventId)->orderByDesc('submittedAt')->get();

        $attended = Attendee::where('eventId', $event->eventId)
            ->where(function ($q) {
                $q->where('isAccredited', true)
                  ->orWhereIn('attendeeId', AttendanceRecord::select('attendeeId'));
            })->count();

        $questions = QuestionnaireQuestion::where('eventId', $event->eventId)
            ->orderBy('sortOrder')->orderBy('questionId')->get()
            ->map(function (QuestionnaireQuestion $q) use ($responses) {
                $values = $responses
                    ->map(fn ($r) => ($r->answers ?? [])[$q->questionId] ?? null)
                    ->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])
                    ->values();

                $out = [
                    'questionId' => $q->questionId,
                    'type'       => $q->type,
                    'prompt'     => $q->prompt,
                    'answered'   => $values->count(),
                ];

                if ($q->type === 'rating') {
                    $out['average'] = $values->count() ? round($values->avg(), 2) : null;
                    $out['distribution'] = collect([5, 4, 3, 2, 1])
                        ->map(fn ($n) => ['label' => (string) $n, 'count' => $values->filter(fn ($v) => (int) $v === $n)->count()])
                        ->values();
                } elseif (in_array($q->type, ['single_choice', 'multi_choice'], true)) {
                    $flat = $q->type === 'multi_choice' ? $values->flatten() : $values;
                    $out['distribution'] = collect($q->options ?? [])
                        ->map(fn ($opt) => ['label' => $opt, 'count' => $flat->filter(fn ($v) => $v === $opt)->count()])
                        ->values();
                } else {
                    $out['answers'] = $values->take(100)->values();
                }

                return $out;
            })->values();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'totalResponses' => $responses->count(),
                'attendedCount'  => $attended,
                'questions'      => $questions,
            ],
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type'      => ['required', 'in:rating,single_choice,multi_choice,text'],
            'prompt'    => ['required', 'string', 'max:500'],
            'required'  => ['required', 'boolean'],
            'options'   => ['nullable', 'array'],
            'options.*' => ['string', 'max:100'],
        ]);

        $isChoice = in_array($data['type'], ['single_choice', 'multi_choice'], true);
        $options = $isChoice
            ? array_values(array_unique(array_filter(array_map('trim', $data['options'] ?? []))))
            : null;

        if ($isChoice && count($options) < 2) {
            return [null, response()->json(['success' => false, 'message' => 'Choice questions need at least two options.'], 422)];
        }

        return [[
            'type'     => $data['type'],
            'prompt'   => trim($data['prompt']),
            'required' => (bool) $data['required'],
            'options'  => $options,
        ], null];
    }
}
