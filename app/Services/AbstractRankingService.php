<?php

namespace App\Services;

use App\Models\AbstractSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AbstractRankingService
{
    private const OVERALL_TOP = 30;
    private const SUB_THEME_TOP = 5;
    
    /**
     * Get overall top 30 abstracts
     */
    public function getOverallRanking(): Collection
    {
        // Use the correct table columns from your database
        return AbstractSubmission::with(['authors', 'assignments.review'])
            // ->where('status', 'scored')
            ->whereNotNull('average_score')
            ->orderBy('average_score', 'desc')
            ->limit(self::OVERALL_TOP)
            ->get()
            ->map(function ($abstract, $index) {
                return $this->enrichWithRank($abstract, $index + 1);
            });
    }
    
    /**
     * Get top 5 abstracts per sub-theme
     */
    public function getSubThemeRanking(): array
    {
        // Get all unique sub-themes from your abstracts table
        $subThemes = AbstractSubmission::
        // where('status', 'accepted')
            // ->
            whereNotNull('average_score')
            ->distinct()
            ->pluck('sub_theme')
            ->toArray();
        
        // If no sub-themes found, return empty array
        if (empty($subThemes)) {
            return [];
        }
        
        $rankings = [];
        foreach ($subThemes as $theme) {
            $rankings[$theme] = AbstractSubmission::with(['authors', 'assignments.review'])
                // ->where('status', 'accepted')
                ->where('sub_theme', $theme)
                ->whereNotNull('average_score')
                ->orderBy('average_score', 'desc')
                ->limit(self::SUB_THEME_TOP)
                ->get()
                ->map(function ($abstract, $index) {
                    return $this->enrichWithRank($abstract, $index + 1);
                });
        }
        
        return $rankings;
    }
    
    /**
     * Get ranking with additional computed fields
     */
    private function enrichWithRank(AbstractSubmission $abstract, int $rank): array
    {
        // Get the review count from assignments
        $reviewCount = $abstract->assignments->where('status', 'submitted')->count();
        
        return [
            'rank' => $rank,
            'abstract' => [
                'id' => $abstract->id,
                'reference' => $abstract->reference,
                'title' => $abstract->title,
                'sub_theme' => $abstract->sub_theme,
                'average_score' => (float) $abstract->average_score,
                'status' => $abstract->status,
                'presentation_type' => $abstract->presentation_type ?? 'pending',
                'authors' => $abstract->authors->map(function ($author) {
                    return [
                        'id' => $author->id,
                        'name' => $author->name,
                        'email' => $author->email,
                        'affiliation' => $author->affiliation,
                        'is_corresponding' => (bool) $author->is_corresponding,
                    ];
                })->toArray(),
                'submitted_at' => $abstract->submitted_at,
                'body' => $abstract->body,
            ],
            'average_score' => (float) $abstract->average_score,
            'review_count' => $reviewCount,
            'sub_theme' => $abstract->sub_theme,
        ];
    }
    
    /**
     * Get statistics for the dashboard
     */
    public function getStatistics(): array
    {
        $acceptedAbstracts = AbstractSubmission::all();
            // ->where('status', 'accepted')
            // ->get();
        // where('status', 'scored')->get();
        
        return [
            'total_accepted' => $acceptedAbstracts->count(),
            'oral_count' => $acceptedAbstracts->where('presentation_type', 'Oral')->count(),
            'poster_count' => $acceptedAbstracts->where('presentation_type', 'Poster')->count(),
            'notifications_sent' => $acceptedAbstracts->whereNotNull('notification_sent_at')->count(),
            'pending_notifications' => $acceptedAbstracts->whereNull('notification_sent_at')->count(),
            'average_score' => $acceptedAbstracts->avg('average_score') ?? 0,
        ];
    }
}