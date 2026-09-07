<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbstractSubmission;
use App\Models\ReviewAssignment;
use App\Models\Reviewer;
use App\Models\Review;
use App\Notifications\AbstractDecisionNotification;
use App\Services\AbstractRankingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;


class AbstractRankingController extends Controller
{
    public function __construct(
        private readonly AbstractRankingService $rankingService
    ) {}

    /**
     * Get all rankings (Admin only)
     * Returns complete rankings of all abstracts
     */
    public function index(): JsonResponse
    {
        try {
            // This endpoint should be protected by admin middleware
            $rankings = [
                'overall' => $this->rankingService->getOverallRanking(),
                'sub_themes' => $this->rankingService->getSubThemeRanking(),
            ];

            return response()->json([
                'success' => true,
                'data' => $rankings,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch admin rankings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rankings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reviewer-specific rankings
     * Returns rankings only for abstracts reviewed by the current user
     */
    public function reviewerRankings(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // First, get the reviewer record for this user
            $reviewer = Reviewer::where('user_id', $user->id)->first();
            
            if (!$reviewer) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not registered as a reviewer.',
                ], 403);
            }

            // Get IDs of abstracts reviewed by this reviewer (completed reviews only)
            // Using the reviewer's ID from the Reviewer model
            $reviewedAbstractIds = ReviewAssignment::where('reviewer_id', $reviewer->id)
                // ->where('status', 'submitted')
                ->pluck('abstract_id')
                ->toArray();

            Log::info('Reviewer rankings request', [
                'user_id' => $user->id,
                'reviewer_id' => $reviewer->id,
                'reviewed_count' => count($reviewedAbstractIds),
                'reviewed_abstract_ids' => $reviewedAbstractIds,
            ]);

            // If no reviews completed, return empty rankings
            if (empty($reviewedAbstractIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'overall' => [],
                        'sub_themes' => (object) [],
                    ],
                    'meta' => [
                        'total_reviewed' => 0,
                        'message' => 'You haven\'t completed any reviews yet.',
                    ],
                ]);
            }

            // Get ALL rankings first
            $allOverallRankings = $this->rankingService->getOverallRanking();
            $allSubThemeRankings = $this->rankingService->getSubThemeRanking();

            // Filter overall rankings to ONLY include reviewed abstracts
            $filteredOverall = [];
            foreach ($allOverallRankings as $item) {
                if (in_array($item['abstract']['id'], $reviewedAbstractIds)) {
                    $filteredOverall[] = $item;
                }
            }

            // Filter sub-theme rankings to ONLY include reviewed abstracts
            $filteredSubThemes = [];
            foreach ($allSubThemeRankings as $theme => $rankings) {
                $filtered = [];
                foreach ($rankings as $item) {
                    if (in_array($item['abstract']['id'], $reviewedAbstractIds)) {
                        $filtered[] = $item;
                    }
                }
                // Only include themes that have reviewed abstracts
                if (!empty($filtered)) {
                    $filteredSubThemes[$theme] = $filtered;
                }
            }

            // Re-index the filtered arrays
            $filteredOverall = array_values($filteredOverall);
            foreach ($filteredSubThemes as $theme => $rankings) {
                $filteredSubThemes[$theme] = array_values($rankings);
            }

            $rankings = [
                'overall' => $filteredOverall,
                'sub_themes' => $filteredSubThemes,
            ];

            return response()->json([
                'success' => true,
                'data' => $rankings,
                'meta' => [
                    'total_reviewed' => count($reviewedAbstractIds),
                    'total_rankings' => count($filteredOverall),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch reviewer rankings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviewer rankings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
 * Classify abstracts and send notifications (Admin only)
 */
public function classifyAndNotify(Request $request): JsonResponse
{
    $request->validate([
        'dry_run' => 'boolean',
    ]);

    $dryRun = $request->boolean('dry_run', false);

    try {
        DB::beginTransaction();

        // Get rankings
        $rankings = [
            'overall' => $this->rankingService->getOverallRanking(),
            'sub_themes' => $this->rankingService->getSubThemeRanking(),
        ];

        // Get all scored abstracts (accepted)
       $acceptedAbstracts = AbstractSubmission::where('status', 'scored')
    ->with(['authors' => function ($query) {
        $query->where('is_corresponding', true);
    }])
    ->get();

        if ($acceptedAbstracts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No accepted abstracts found to classify.',
            ], 400);
        }

        $results = [];
        $notificationsSent = 0;

        foreach ($acceptedAbstracts as $abstract) {
            // Determine presentation type
            $presentationType = $this->determinePresentationType($abstract, $rankings);
            $abstract->update(['presentation_type' => $presentationType]);

            // Get ranking information
            $overallRank = collect($rankings['overall'])
                ->firstWhere('abstract.id', $abstract->id);

            $subThemeRank = collect($rankings['sub_themes'][$abstract->sub_theme] ?? [])
                ->firstWhere('abstract.id', $abstract->id);

            // Determine decision
            $decision = $presentationType;

            // Get corresponding author from the loaded relationship
            $author = $abstract->authors->first();

            if ($author && !$dryRun) {
                try {
                    // Send notification
                    // $author->notify(new AbstractDecisionNotification(
                    //     abstract: $abstract,
                    //     decision: $decision,
                    //     rank: $overallRank['rank'] ?? null,
                    //     subTheme: $abstract->sub_theme,
                    //     subThemeRank: $subThemeRank['rank'] ?? null
                    // ));

                    $author->notify(new AbstractDecisionNotification(
    abstract: $abstract,
    presentationType: $presentationType,
    rank: $overallRank['rank'] ?? null,
    subTheme: $abstract->sub_theme,
    subThemeRank: $subThemeRank['rank'] ?? null
));
                    
                    // Update notification sent timestamp
                    $abstract->update(['notification_sent_at' => now()]);
                    $notificationsSent++;
                } catch (\Exception $e) {
                    Log::error('Failed to send notification to author', [
                        'abstract_id' => $abstract->id,
                        'author_id' => $author->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $results[] = [
                'abstract_id' => $abstract->id,
                'title' => $abstract->title,
                'presentation_type' => $presentationType,
                'score' => $abstract->average_score,
                'overall_rank' => $overallRank['rank'] ?? null,
                'sub_theme_rank' => $subThemeRank['rank'] ?? null,
                'author_email' => $author->email ?? null,
                'notification_sent' => !$dryRun && $author !== null,
            ];
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => $dryRun 
                ? "Dry run completed. " . count($results) . " abstracts classified. No notifications sent."
                : "Classification completed. {$notificationsSent} notifications sent.",
            'results' => $results,
            'notifications_sent' => $notificationsSent,
            'dry_run' => $dryRun,
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to classify abstracts and send notifications', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to process abstracts: ' . $e->getMessage(),
        ], 500);
    }
}
    /**
     * Determine presentation type based on ranking
     */
    private function determinePresentationType(AbstractSubmission $abstract, array $rankings): string
    {
        // Check if in overall top 30
        $overallRank = collect($rankings['overall'] ?? [])
            ->firstWhere('abstract.id', $abstract->id);
            
        if ($overallRank && $overallRank['rank'] <= 30) {
            return 'oral';
        }
        
        // Check if in sub-theme top 5
        $subThemeRank = collect($rankings['sub_themes'][$abstract->sub_theme] ?? [])
            ->firstWhere('abstract.id', $abstract->id);
            
        if ($subThemeRank && $subThemeRank['rank'] <= 5) {
            return 'oral';
        }
        
        return 'poster';
    }

    /**
     * Resend notification to a specific author
     */
    // use Illuminate\Support\Facades\Notification;

public function resendNotification(Request $request, int $abstractId): JsonResponse
{
    try {
        $abstract = AbstractSubmission::with([
            'correspondingAuthor'
        ])->findOrFail($abstractId);

        if ($abstract->status !== 'scored') {
            return response()->json([
                'success' => false,
                'message' => 'Only scored abstracts have decisions.',
            ], 400);
        }

        // Get corresponding author
        $author = $abstract->correspondingAuthor;

        if (!$author) {
            return response()->json([
                'success' => false,
                'message' => 'No corresponding author found for this abstract.',
            ], 404);
        }

        // Make sure author has an email
        if (empty($author->email)) {
            return response()->json([
                'success' => false,
                'message' => 'The corresponding author does not have an email address.',
            ], 400);
        }

        // Get rankings
        $rankings = [
            'overall' => $this->rankingService->getOverallRanking(),
            'sub_themes' => $this->rankingService->getSubThemeRanking(),
        ];

        $overallRank = collect($rankings['overall'])
            ->firstWhere('abstract.id', $abstractId);

        $subThemeRank = collect(
            $rankings['sub_themes'][$abstract->sub_theme] ?? []
        )->firstWhere('abstract.id', $abstractId);

        // Determine presentation type
        $presentationType = $abstract->presentation_type ?? 'poster';

        // Send email notification directly to author's email
        Notification::route('mail', $author->email)
            ->notify(new AbstractDecisionNotification(
                abstract: $abstract,
                presentationType: $presentationType,
                rank: $overallRank['rank'] ?? null,
                subTheme: $abstract->sub_theme,
                subThemeRank: $subThemeRank['rank'] ?? null
            ));

        // Update notification timestamp
        $abstract->update([
            'notification_sent_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification resent successfully.',
            'author_email' => $author->email,
        ]);

    } catch (\Exception $e) {
        Log::error('Failed to resend notification', [
            'abstract_id' => $abstractId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to send notification: ' . $e->getMessage(),
        ], 500);
    }
}

    /**
     * Export rankings as CSV
     * Admin gets all rankings, reviewer gets only their reviewed abstracts
     */
    public function export(Request $request)
    {
        try {
            $user = $request->user();
            $isAdmin = $user->hasRole('admin');
            
            if ($isAdmin) {
                // Admin: Export all rankings
                $rankings = $this->rankingService->getOverallRanking();
                $filename = 'rankings_all_' . date('Y-m-d') . '.csv';
            } else {
                // Reviewer: Get the reviewer record
                $reviewer = Reviewer::where('user_id', $user->id)->first();
                
                if (!$reviewer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not registered as a reviewer.',
                    ], 403);
                }
                
                // Export only their reviewed abstracts
                $reviewedAbstractIds = ReviewAssignment::where('reviewer_id', $reviewer->id)
                    ->where('status', 'submitted')
                    ->pluck('abstract_id')
                    ->toArray();
                    
                if (empty($reviewedAbstractIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You haven\'t reviewed any abstracts yet.',
                    ], 400);
                }
                
                $allRankings = $this->rankingService->getOverallRanking();
                $rankings = array_filter($allRankings, function($item) use ($reviewedAbstractIds) {
                    return in_array($item['abstract']['id'], $reviewedAbstractIds);
                });
                $rankings = array_values($rankings);
                $filename = 'reviewer_rankings_' . date('Y-m-d') . '.csv';
            }

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function() use ($rankings) {
                $file = fopen('php://output', 'w');
                
                // Add headers
                fputcsv($file, [
                    'Rank',
                    'Reference',
                    'Title',
                    'Sub-Theme',
                    'Score',
                    'Review Count',
                    'Presentation Type',
                    'Authors',
                ]);

                // Add data
                foreach ($rankings as $item) {
                    fputcsv($file, [
                        $item['rank'],
                        $item['abstract']['reference'],
                        $item['abstract']['title'],
                        $item['sub_theme'],
                        $item['average_score'],
                        $item['review_count'],
                        $item['abstract']['presentation_type'] ?? 'Pending',
                        implode('; ', collect($item['abstract']['authors'])->pluck('name')->toArray()),
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Failed to export rankings', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export rankings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for reviewer
     * Shows stats only for abstracts reviewed by the user
     */
    public function reviewerStatistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get the reviewer record
            $reviewer = Reviewer::where('user_id', $user->id)->first();
            
            if (!$reviewer) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not registered as a reviewer.',
                ], 403);
            }
            
            $reviewedAbstractIds = ReviewAssignment::where('reviewer_id', $reviewer->id)
                ->where('status', 'submitted')
                ->pluck('abstract_id')
                ->toArray();

            if (empty($reviewedAbstractIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_reviewed' => 0,
                        'average_score' => 0,
                        'highest_score' => 0,
                        'lowest_score' => 0,
                        'by_theme' => [],
                    ],
                ]);
            }

            $abstracts = AbstractSubmission::whereIn('id', $reviewedAbstractIds)
                ->whereNotNull('average_score')
                ->get();

            $scores = $abstracts->pluck('average_score')->filter();
            $avgScore = $scores->isNotEmpty() ? $scores->avg() : 0;
            
            $byTheme = $abstracts->groupBy('sub_theme')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'average_score' => $group->avg('average_score'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_reviewed' => $abstracts->count(),
                    'average_score' => round($avgScore, 2),
                    'highest_score' => $scores->isNotEmpty() ? round($scores->max(), 2) : 0,
                    'lowest_score' => $scores->isNotEmpty() ? round($scores->min(), 2) : 0,
                    'by_theme' => $byTheme,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch reviewer statistics', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
}