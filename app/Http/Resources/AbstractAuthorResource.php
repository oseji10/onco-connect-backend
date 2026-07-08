<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbstractAuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'affiliation' => $this->affiliation,
            'email' => $this->email,
            'isCorresponding' => (bool) $this->is_corresponding,
        ];
    }
}