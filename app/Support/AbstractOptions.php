<?php

namespace App\Support;

class AbstractOptions
{
    public const SUB_THEMES = [
        'policy-leadership-governance',
        'research-innovation-data',
        'quality-care-implementation',
    ];

    public const PRESENTATION_TYPES = ['Oral', 'Poster', 'Either'];

    public const ABSTRACT_WORD_LIMIT = 500;

    public const REJECTION_REASONS = [
        'Irrelevance — subject is not relevant to the cancer field',
        'Plagiarism — abstract is a copy of other work',
        'Too short — abstract does not provide enough detail',
        'Late submission — submitted after the deadline',
    ];

    public static function countWords(string $text): int
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $trimmed));
    }
}