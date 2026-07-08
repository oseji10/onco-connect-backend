<?php

namespace App\Http\Requests;

use App\Support\AbstractOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level middleware confirms this user is the assigned reviewer.
        return true;
    }

    public function rules(): array
    {
        return [
            'scores' => ['required', 'array'],
            'scores.significance' => ['required', 'integer', 'between:1,5'],
            'scores.relevance' => ['required', 'integer', 'between:1,5'],
            'scores.originality' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'recommendedRejectionReason' => [
                'nullable',
                'string',
                Rule::in(AbstractOptions::REJECTION_REASONS),
            ],
        ];
    }
}