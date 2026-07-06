<?php

// Place at: config/sponsorship.php
// Keys must stay in sync with the front end's constants.

return [
    'types' => ['Sponsor', 'Partner'],

    'statuses' => [
        'prospect'      => 'Prospect',
        'contacted'     => 'Contacted',
        'proposal_sent' => 'Proposal Sent',
        'negotiation'   => 'Negotiation',
        'committed'     => 'Committed',
        'confirmed'     => 'Confirmed',
        'declined'      => 'Declined',
    ],

    'payment_statuses' => ['unpaid', 'partial', 'paid'],

    'deliverable_statuses' => ['pending', 'in_progress', 'fulfilled'],

    'document_categories' => ['MOU', 'Contract', 'Invoice', 'Proposal', 'Logo', 'Other'],

    'tiers' => [
        'platinum' => [
            'label'    => 'Platinum',
            'benefits' => [
                'Prime logo placement',
                'Keynote speaking slot',
                'Premium exhibition booth',
                'Full-page program advert',
                '10 delegate passes',
            ],
        ],
        'gold' => [
            'label'    => 'Gold',
            'benefits' => [
                'Logo on event materials',
                'Panel speaking slot',
                'Standard exhibition booth',
                'Half-page program advert',
                '6 delegate passes',
            ],
        ],
        'silver' => [
            'label'    => 'Silver',
            'benefits' => [
                'Logo on website',
                'Shared booth space',
                'Quarter-page advert',
                '4 delegate passes',
            ],
        ],
        'bronze' => [
            'label'    => 'Bronze',
            'benefits' => [
                'Logo on website',
                'Listing in program',
                '2 delegate passes',
            ],
        ],
        'partner' => [
            'label'    => 'Partner (Non-monetary)',
            'benefits' => [
                'Logo as official partner',
                'Acknowledgement in communications',
            ],
        ],
    ],
];