<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealRating;
use App\Models\MealSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MealRatingStatisticsController extends Controller
{
    /**
     * Get statistics and detailed feedback for all meal sessions
     * NOW INCLUDES: Food supply information for each meal
     */
    public function statistics(): JsonResponse
    {
        $mealSessions = MealSession::with([
                'ratings' => function ($query) {
                    $query->orderByDesc('created_at');
                },
                'food_supplies' => function ($query) {
                    $query->orderBy('foodItem');
                }
            ])
            ->whereHas('ratings') // Only include meals that have at least one rating
            ->orderByDesc('mealDate')
            ->get();

        $statistics = $mealSessions->map(function ($session) {
            $ratings = $session->ratings;
            
            // Calculate average rating
            $averageRating = $ratings->avg('rating') ?? 0;
            
            // Calculate rating distribution
            $distribution = [
                1 => $ratings->where('rating', 1)->count(),
                2 => $ratings->where('rating', 2)->count(),
                3 => $ratings->where('rating', 3)->count(),
                4 => $ratings->where('rating', 4)->count(),
                5 => $ratings->where('rating', 5)->count(),
            ];

            // Format food supply information
            $foodSupplies = $session->food_supplies->map(function ($supply) {
                return [
                    'supplyId' => $supply->supplyId,
                    'foodItem' => $supply->foodItem,
                    'vendorName' => $supply->vendorName,
                    'quantitySupplied' => $supply->quantitySupplied,
                    'quantityDistributed' => $supply->quantityDistributed,
                    'quantityRemaining' => $supply->quantityRemaining,
                    'supplyDate' => $supply->supplyDate ? 
                        (is_string($supply->supplyDate) ? $supply->supplyDate : $supply->supplyDate->format('Y-m-d')) 
                        : null,
                    'notes' => $supply->notes,
                ];
            })->values()->all();

            // Calculate supply totals
            $totalSupplied = $session->food_supplies->sum('quantitySupplied');
            $totalDistributed = $session->food_supplies->sum('quantityDistributed');
            $totalRemaining = $session->food_supplies->sum('quantityRemaining');

            // Safely format mealDate
            $mealDate = $session->mealDate;
            if (is_string($mealDate)) {
                $mealDate = Carbon::parse($mealDate)->format('Y-m-d H:i:s');
            } elseif ($mealDate instanceof \DateTimeInterface) {
                $mealDate = $mealDate->format('Y-m-d H:i:s');
            } else {
                $mealDate = null;
            }

            return [
                'mealSessionId' => $session->mealSessionId,
                'mealSessionTitle' => $session->title,
                'mealDate' => $mealDate,
                'foodItems' => $session->foodItems ?? [],
                'vendors' => $session->vendors ?? [],
                
                // Rating information
                'totalRatings' => $ratings->count(),
                'averageRating' => round($averageRating, 2),
                'ratingDistribution' => $distribution,
                'ratings' => $ratings->map(function ($rating) {
                    return [
                        'ratingId' => $rating->ratingId,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'createdAt' => $rating->created_at->format('Y-m-d H:i:s'),
                    ];
                })->values()->all(),

                // Food supply information
                'foodSupplies' => $foodSupplies,
                'supplyTotals' => [
                    'totalSupplied' => $totalSupplied,
                    'totalDistributed' => $totalDistributed,
                    'totalRemaining' => $totalRemaining,
                    'distributionRate' => $totalSupplied > 0 
                        ? round(($totalDistributed / $totalSupplied) * 100, 1) 
                        : 0,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Meal feedback statistics retrieved successfully.',
            'data' => [
                'meals' => $statistics->values()->all(),
            ],
        ]);
    }

    /**
     * Get detailed statistics for a specific meal session
     */
    public function show(MealSession $mealSession): JsonResponse
    {
        $mealSession->load(['ratings', 'food_supplies']);
        
        $ratings = $mealSession->ratings()->orderByDesc('created_at')->get();

        $averageRating = $ratings->avg('rating') ?? 0;
        
        $distribution = [
            1 => $ratings->where('rating', 1)->count(),
            2 => $ratings->where('rating', 2)->count(),
            3 => $ratings->where('rating', 3)->count(),
            4 => $ratings->where('rating', 4)->count(),
            5 => $ratings->where('rating', 5)->count(),
        ];

        // Group ratings by date
        $ratingsByDate = $ratings->groupBy(function ($rating) {
            return $rating->created_at->format('Y-m-d');
        })->map(function ($dayRatings) {
            return [
                'count' => $dayRatings->count(),
                'average' => round($dayRatings->avg('rating'), 2),
            ];
        });

        // Format food supplies
        $foodSupplies = $mealSession->food_supplies->map(function ($supply) {
            return [
                'supplyId' => $supply->supplyId,
                'foodItem' => $supply->foodItem,
                'vendorName' => $supply->vendorName,
                'quantitySupplied' => $supply->quantitySupplied,
                'quantityDistributed' => $supply->quantityDistributed,
                'quantityRemaining' => $supply->quantityRemaining,
                'supplyDate' => $supply->supplyDate ? 
                    (is_string($supply->supplyDate) ? $supply->supplyDate : $supply->supplyDate->format('Y-m-d')) 
                    : null,
                'notes' => $supply->notes,
            ];
        })->values()->all();

        // Safely format mealDate
        $mealDate = $mealSession->mealDate;
        if (is_string($mealDate)) {
            $mealDate = Carbon::parse($mealDate)->format('Y-m-d H:i:s');
        } elseif ($mealDate instanceof \DateTimeInterface) {
            $mealDate = $mealDate->format('Y-m-d H:i:s');
        } else {
            $mealDate = null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Meal statistics retrieved successfully.',
            'data' => [
                'mealSession' => [
                    'mealSessionId' => $mealSession->mealSessionId,
                    'mealSessionTitle' => $mealSession->title,
                    'mealDate' => $mealDate,
                    'foodItems' => $mealSession->foodItems ?? [],
                    'vendors' => $mealSession->vendors ?? [],
                ],
                'statistics' => [
                    'totalRatings' => $ratings->count(),
                    'averageRating' => round($averageRating, 2),
                    'ratingDistribution' => $distribution,
                    'commentsCount' => $ratings->whereNotNull('comment')->where('comment', '!=', '')->count(),
                    'ratingsByDate' => $ratingsByDate,
                ],
                'ratings' => $ratings->map(function ($rating) {
                    return [
                        'ratingId' => $rating->ratingId,
                        'rating' => $rating->rating,
                        'comment' => $rating->comment,
                        'createdAt' => $rating->created_at->format('Y-m-d H:i:s'),
                    ];
                })->values()->all(),
                'foodSupplies' => $foodSupplies,
                'supplyTotals' => [
                    'totalSupplied' => $mealSession->food_supplies->sum('quantitySupplied'),
                    'totalDistributed' => $mealSession->food_supplies->sum('quantityDistributed'),
                    'totalRemaining' => $mealSession->food_supplies->sum('quantityRemaining'),
                ],
            ],
        ]);
    }

    /**
     * Get overall statistics across all meal sessions
     */
    public function overall(): JsonResponse
    {
        $allRatings = MealRating::all();
        
        if ($allRatings->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No ratings available yet.',
                'data' => [
                    'totalRatings' => 0,
                    'averageRating' => 0,
                    'totalMealSessions' => 0,
                    'ratingDistribution' => [
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                    ],
                ],
            ]);
        }

        $averageRating = $allRatings->avg('rating') ?? 0;
        
        $distribution = [
            1 => $allRatings->where('rating', 1)->count(),
            2 => $allRatings->where('rating', 2)->count(),
            3 => $allRatings->where('rating', 3)->count(),
            4 => $allRatings->where('rating', 4)->count(),
            5 => $allRatings->where('rating', 5)->count(),
        ];

        $totalMealSessions = MealSession::whereHas('ratings')->count();

        // Overall food supply statistics
        $totalSupplied = DB::table('food_supplies')->sum('quantitySupplied');
        $totalDistributed = DB::table('food_supplies')->sum('quantityDistributed');
        $totalRemaining = DB::table('food_supplies')->sum('quantityRemaining');

        return response()->json([
            'success' => true,
            'message' => 'Overall statistics retrieved successfully.',
            'data' => [
                'totalRatings' => $allRatings->count(),
                'averageRating' => round($averageRating, 2),
                'totalMealSessions' => $totalMealSessions,
                'ratingDistribution' => $distribution,
                'commentsCount' => $allRatings->whereNotNull('comment')->where('comment', '!=', '')->count(),
                'supplyTotals' => [
                    'totalSupplied' => $totalSupplied,
                    'totalDistributed' => $totalDistributed,
                    'totalRemaining' => $totalRemaining,
                    'distributionRate' => $totalSupplied > 0 
                        ? round(($totalDistributed / $totalSupplied) * 100, 1) 
                        : 0,
                ],
            ],
        ]);
    }

    /**
     * Export meal ratings to CSV
     */
    public function export(): JsonResponse
    {
        $ratings = MealRating::with('mealSession')
            ->orderByDesc('created_at')
            ->get();

        $csvData = [];
        $csvData[] = [
            'Meal Session',
            'Meal Date',
            'Food Items',
            'Vendors',
            'Rating',
            'Comment',
            'Submitted At',
        ];

        foreach ($ratings as $rating) {
            $mealDate = $rating->mealSession->mealDate ?? null;
            if ($mealDate) {
                $mealDate = is_string($mealDate) 
                    ? Carbon::parse($mealDate)->format('Y-m-d') 
                    : $mealDate->format('Y-m-d');
            }

            $csvData[] = [
                $rating->mealSession->title ?? 'N/A',
                $mealDate ?? 'N/A',
                is_array($rating->mealSession->foodItems) 
                    ? implode(', ', $rating->mealSession->foodItems) 
                    : '',
                is_array($rating->mealSession->vendors) 
                    ? implode(', ', $rating->mealSession->vendors) 
                    : '',
                $rating->rating,
                $rating->comment ?? '',
                $rating->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Export data generated.',
            'data' => [
                'csv' => $csvData,
                'totalRows' => count($csvData) - 1, // Exclude header
            ],
        ]);
    }
}