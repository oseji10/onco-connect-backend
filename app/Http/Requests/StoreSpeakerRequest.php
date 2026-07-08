<?php

namespace App\Http\Requests;

use App\Support\SpeakerOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSpeakerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public submission endpoint.
        return true;
    }

    public function rules(): array
    {
        return [
            'sessionType' => ['required', 'string', Rule::in(SpeakerOptions::SESSION_TYPES)],
            'subTheme' => ['required', 'string', Rule::in(SpeakerOptions::SUB_THEMES)],
            'sessionTitle' => ['required', 'string', 'max:500'],
            'sessionDescription' => ['required', 'string'],
            'participationType' => ['required', 'string', Rule::in(SpeakerOptions::PARTICIPATION_TYPES)],

            'title' => ['required', 'string', 'max:50'],
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'otherNames' => ['nullable', 'string', 'max:255'],
            'organization' => ['required', 'string', 'max:255'],
            'jobTitle' => ['required', 'string', 'max:255'],
            'bio' => ['required', 'string'],
            'physicallyChallenged' => ['required', 'boolean'],
            'accessibilityNeeds' => ['nullable', 'string', 'max:2000'],

            'email' => ['required', 'email', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'phoneCountryCode' => ['nullable', 'string', 'max:10'],
            'phoneNumber' => ['required', 'string', 'max:20'],
            'linkedinUrl' => ['nullable', 'string', 'max:255'],
            'twitterHandle' => ['nullable', 'string', 'max:100'],

            'photo' => ['required', 'image', 'max:5120'], // 5MB
            'cv' => ['required', 'mimes:pdf,doc,docx', 'max:10240'], // 10MB
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bioWords = SpeakerOptions::countWords((string) $this->input('bio', ''));
            if ($bioWords > SpeakerOptions::BIO_WORD_LIMIT) {
                $validator->errors()->add(
                    'bio',
                    sprintf('Bio exceeds the %d-word limit (currently %d).', SpeakerOptions::BIO_WORD_LIMIT, $bioWords)
                );
            }

            $descWords = SpeakerOptions::countWords((string) $this->input('sessionDescription', ''));
            if ($descWords > SpeakerOptions::SESSION_DESCRIPTION_WORD_LIMIT) {
                $validator->errors()->add(
                    'sessionDescription',
                    sprintf(
                        'Session description exceeds the %d-word limit (currently %d).',
                        SpeakerOptions::SESSION_DESCRIPTION_WORD_LIMIT,
                        $descWords
                    )
                );
            }
        });
    }
}