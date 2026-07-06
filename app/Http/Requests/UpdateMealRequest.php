<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMealRequest extends FormRequest
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
            'mealDate' => ['sometimes', 'date'],
            'startTime' => ['sometimes', 'date_format:H:i'],
            'endTime' => ['sometimes', 'date_format:H:i'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:draft,active,closed,cancelled'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            // Only validate if both are present
            if ($start && $end && $end <= $start) {
                $validator->errors()->add(
                    'end_time',
                    'The end time must be after the start time.'
                );
            }
        });
    }
}