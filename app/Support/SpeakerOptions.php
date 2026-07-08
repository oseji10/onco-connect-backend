<?php

namespace App\Support;

class SpeakerOptions
{
    public const SESSION_TYPES = ['Keynote', 'Plenary', 'Panel', 'Breakout'];

    public const SUB_THEMES = [
        'policy-leadership-governance',
        'research-innovation-data',
        'quality-care-implementation',
    ];

    public const PARTICIPATION_TYPES = ['Physical', 'Virtual'];

    public const BIO_WORD_LIMIT = 150;
    public const SESSION_DESCRIPTION_WORD_LIMIT = 300;

    public static function countWords(string $text): int
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $trimmed));
    }
}