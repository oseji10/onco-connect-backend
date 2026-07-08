<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewerInvitePreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'affiliation' => $this->affiliation,
            'alreadyHasAccount' => (bool) $this->user_id,
        ];
    }
}