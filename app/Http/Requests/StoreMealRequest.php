<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'mealDate' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'ticketCount' => ['required', 'integer', 'min:1'],
        ];
    }
}