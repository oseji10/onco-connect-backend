<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMealSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'mealDate' => ['sometimes', 'date'],
            'startTime' => ['sometimes', 'date_format:H:i'],
            'endTime' => ['sometimes', 'date_format:H:i'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:draft,active,closed,cancelled'],
        ];
    }
}