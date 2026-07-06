<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RedeemPassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'deviceName' => ['nullable', 'string', 'max:255'],
            'eventId' => ['nullable', 'integer'],
        ];
    }
}