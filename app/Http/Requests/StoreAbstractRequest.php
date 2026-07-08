<?php

namespace App\Http\Requests;

use App\Support\AbstractOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAbstractRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public submission endpoint — anyone may submit.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'subTheme' => ['required', 'string', Rule::in(AbstractOptions::SUB_THEMES)],
            'presentationType' => ['required', 'string', Rule::in(AbstractOptions::PRESENTATION_TYPES)],
            'keywords' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],

            'authors' => ['required', 'array', 'min:1'],
            'authors.*.name' => ['required', 'string', 'max:255'],
            'authors.*.affiliation' => ['required', 'string', 'max:255'],
            'authors.*.email' => ['nullable', 'email', 'max:255'],
            'authors.*.isCorresponding' => ['boolean'],
        ];
    }

    /**
     * Re-derive the word count server-side rather than trusting the client's
     * count, and reject anything over the limit.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $body = (string) $this->input('body', '');
            $wordCount = AbstractOptions::countWords($body);

            if ($wordCount > AbstractOptions::ABSTRACT_WORD_LIMIT) {
                $validator->errors()->add(
                    'body',
                    sprintf(
                        'Abstract exceeds the %d-word limit (currently %d).',
                        AbstractOptions::ABSTRACT_WORD_LIMIT,
                        $wordCount
                    )
                );
            }

            $authors = $this->input('authors', []);
            $hasCorresponding = collect($authors)->contains(
                fn ($a) => filter_var($a['isCorresponding'] ?? false, FILTER_VALIDATE_BOOLEAN)
            );

            if ($hasCorresponding) {
                $corresponding = collect($authors)->first(
                    fn ($a) => filter_var($a['isCorresponding'] ?? false, FILTER_VALIDATE_BOOLEAN)
                );
                if (empty($corresponding['email'] ?? null)) {
                    $validator->errors()->add(
                        'authors',
                        'The corresponding author needs an email address.'
                    );
                }
            } else {
                $validator->errors()->add(
                    'authors',
                    'Please designate one corresponding author.'
                );
            }
        });
    }

    public function wordCount(): int
    {
        return AbstractOptions::countWords((string) $this->input('body', ''));
    }
}