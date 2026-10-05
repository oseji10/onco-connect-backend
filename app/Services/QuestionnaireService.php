<?php
// app/Services/QuestionnaireService.php
// Shared by the logged-in controller AND the public (link) controller.

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\QuestionnaireQuestion;
use App\Models\QuestionnaireResponse;

class QuestionnaireService
{
    public function __construct(protected EligibilityService $eligibility) {}

    public function payload(Event $event, Attendee $attendee): array
    {
        $questions = QuestionnaireQuestion::where('eventId', $event->eventId)
            ->orderBy('sortOrder')->orderBy('questionId')
            ->get()
            ->map(fn ($q) => [
                'questionId' => $q->questionId,
                'type'       => $q->type,
                'prompt'     => $q->prompt,
                'options'    => $q->options,
                'required'   => (bool) $q->required,
            ]);

        $response = QuestionnaireResponse::where('attendeeId', $attendee->attendeeId)->first();

        return [
            'firstName'           => $attendee->firstName,
            'status'              => $event->questionnaireStatus ?? 'closed',
            'submitted'           => (bool) $response,
            'answers'             => $response ? (object) $response->answers : (object) [],
            'certificateEligible' => (bool) $attendee->certificateEligible,
            'questions'           => $questions,
        ];
    }

    /** @return array{0:int,1:array} [httpStatus, jsonBody] */
    public function submit(Event $event, Attendee $attendee, mixed $input): array
    {
        if (($event->questionnaireStatus ?? 'closed') !== 'open') {
            return [422, ['success' => false, 'message' => 'The questionnaire is not open right now.']];
        }

        if (!is_array($input)) {
            return [422, ['success' => false, 'message' => 'Invalid answers.']];
        }

        $questions = QuestionnaireQuestion::where('eventId', $event->eventId)->get();
        $clean = [];
        $errors = [];

        foreach ($questions as $q) {
            $id  = $q->questionId;
            $raw = $input[$id] ?? null;
            if (is_string($raw)) $raw = trim($raw);

            if ($raw === null || $raw === '' || $raw === []) {
                if ($q->required) $errors["answers.$id"] = ['This question is required.'];
                continue;
            }

            $options = $q->options ?? [];

            switch ($q->type) {
                case 'rating':
                    if (!is_numeric($raw) || (int) $raw < 1 || (int) $raw > 5) {
                        $errors["answers.$id"] = ['Choose a rating from 1 to 5.'];
                    } else {
                        $clean[$id] = (int) $raw;
                    }
                    break;

                case 'single_choice':
                    if (!is_string($raw) || !in_array($raw, $options, true)) {
                        $errors["answers.$id"] = ['Choose one of the options.'];
                    } else {
                        $clean[$id] = $raw;
                    }
                    break;

                case 'multi_choice':
                    $values = is_array($raw) ? array_values(array_unique($raw)) : null;
                    if ($values === null || array_diff($values, $options)) {
                        $errors["answers.$id"] = ['Choose from the listed options.'];
                    } else {
                        $clean[$id] = $values;
                    }
                    break;

                default: // text
                    if (!is_string($raw) || mb_strlen($raw) > 2000) {
                        $errors["answers.$id"] = ['Keep your answer under 2000 characters.'];
                    } else {
                        $clean[$id] = $raw;
                    }
            }
        }

        if ($errors) {
            return [422, ['success' => false, 'message' => 'Please answer all required questions.', 'errors' => $errors]];
        }

        QuestionnaireResponse::updateOrCreate(
            ['attendeeId' => $attendee->attendeeId],
            ['eventId' => $event->eventId, 'answers' => $clean, 'submittedAt' => now()]
        );

        $eligible = $this->eligibility->recalculate($attendee->fresh());

        return [200, [
            'success' => true,
            'message' => $eligible
                ? 'Thank you! Your certificate is ready to download.'
                : 'Thank you! Your responses were received. Your certificate unlocks once your attendance is confirmed.',
            'data'    => ['certificateEligible' => $eligible],
        ]];
    }
}