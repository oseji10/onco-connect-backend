<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'startDate' => ['sometimes', 'date'],
            'endDate' => ['sometimes', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:draft,active,closed,cancelled'],
        ];
    }
}