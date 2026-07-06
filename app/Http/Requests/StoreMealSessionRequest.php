<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'mealDate' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
        ];
    }
}