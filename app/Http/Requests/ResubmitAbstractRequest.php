<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResubmitAbstractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:500',
            ],

            'subTheme' => [
                'required',
                'string',
                'max:255',
            ],

            'presentationType' => [
                'required',
                'string',
                'max:255',
            ],

            'keywords' => [
                'nullable',
                'string',
                'max:500',
            ],

            'body' => [
                'required',
                'string',
            ],

            'resubmissionNote' => [
                'required',
                'string',
                'max:2000',
            ],

            'authors' => [
                'sometimes',
                'array',
                'min:1',
            ],

            'authors.*.id' => [
                'nullable',
                'string',
            ],

            'authors.*.name' => [
                'required_with:authors',
                'string',
                'max:255',
            ],

            'authors.*.affiliation' => [
                'required_with:authors',
                'string',
                'max:255',
            ],

            'authors.*.email' => [
                'nullable',
                'email',
            ],

            'authors.*.phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            'authors.*.isCorresponding' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $body = (string) $this->input('body', '');

            $wordCount = str_word_count(strip_tags($body));

            /*
             * Keep this aligned with the word limit already used
             * by your abstract submission workflow.
             *
             * If your existing submission controller uses a different
             * limit, use that same value here.
             */
            $wordLimit = 500;

            if ($wordCount > $wordLimit) {
                $validator->errors()->add(
                    'body',
                    "Abstract exceeds the {$wordLimit}-word limit (currently {$wordCount} words)."
                );
            }
        });
    }
}