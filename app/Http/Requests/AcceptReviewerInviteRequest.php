<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptReviewerInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public — gated by the invite token itself, not auth middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}