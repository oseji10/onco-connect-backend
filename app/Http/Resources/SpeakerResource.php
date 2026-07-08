<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpeakerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,

            'sessionType' => $this->session_type,
            'subTheme' => $this->sub_theme,
            'sessionTitle' => $this->session_title,
            'sessionDescription' => $this->session_description,
            'participationType' => $this->participation_type,

            'title' => $this->title,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'otherNames' => $this->other_names,
            'fullName' => $this->full_name,
            'organization' => $this->organization,
            'jobTitle' => $this->job_title,
            'bio' => $this->bio,
            'physicallyChallenged' => (bool) $this->physically_challenged,
            'accessibilityNeeds' => $this->accessibility_needs,

            'email' => $this->email,
            'country' => $this->country,
            'state' => $this->state,
            'phoneCountryCode' => $this->phone_country_code,
            'phoneNumber' => $this->phone_number,
            'linkedinUrl' => $this->linkedin_url,
            'twitterHandle' => $this->twitter_handle,

            'photoUrl' => $this->photo_path ? asset('storage/' . $this->photo_path) : null,
            'cvUrl' => $this->cv_path ? asset('storage/' . $this->cv_path) : null,

            'status' => $this->status,
            'submittedAt' => optional($this->submitted_at)->toIso8601String(),
        ];
    }
}