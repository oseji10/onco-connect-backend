<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignReviewersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewerIds' => ['required', 'array', 'min:2'],
            'reviewerIds.*' => ['integer', 'exists:reviewers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'reviewerIds.min' => 'Assign at least two reviewers per abstract.',
        ];
    }
}