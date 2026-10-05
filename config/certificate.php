<?php
// Save as config/certificate.php

return [
    'organization' => env('CERT_ORGANIZATION', 'Organising Committee'),

    // Leave null to use the event's name/title column.
    'event_name' => env('CERT_EVENT_NAME'),

    // Base URL of the static frontend. Invite links become {frontend_url}/q#<token>
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    // Relative to /public, e.g. public/images/logo.png. Set to null for no logo.
    'logo_path' => 'images/logo.png',

    // Up to three work well on landscape A4.
    'signatories' => [
        ['name' => 'Full Name', 'title' => 'Chairman, Organising Committee'],
        ['name' => 'Full Name', 'title' => 'Director, Programme'],
    ],
];