<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gate at the route level (auth:sanctum + committee role); see routes/api.php.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:reviewers,email'],
            'affiliation' => ['nullable', 'string', 'max:255'],
        ];
    }
}