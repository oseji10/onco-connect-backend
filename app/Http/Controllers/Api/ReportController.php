<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function summary(Request $request, Meal $meal): JsonResponse
    {
        $totalTickets = $meal->tickets()->count();
        $redeemedTickets = $meal->tickets()->where('status', 'redeemed')->count();
        $unusedTickets = $meal->tickets()->where('status', 'unused')->count();
        $voidTickets = $meal->tickets()->where('status', 'void')->count();

        $scanStats = $meal->scanLogs()
            ->selectRaw('scan_result, COUNT(*) as total')
            ->groupBy('scan_result')
            ->pluck('total', 'scan_result');

        return response()->json([
            'success' => true,
            'data' => [
                'meal' => [
                    'id' => $meal->id,
                    'title' => $meal->title,
                    'meal_date' => $meal->meal_date,
                    'start_time' => $meal->start_time,
                    'end_time' => $meal->end_time,
                    'location' => $meal->location,
                    'status' => $meal->status,
                ],
                'totals' => [
                    'tickets' => $totalTickets,
                    'redeemed' => $redeemedTickets,
                    'unused' => $unusedTickets,
                    'void' => $voidTickets,
                ],
                'scan_stats' => [
                    'valid' => (int) ($scanStats['valid'] ?? 0),
                    'invalid' => (int) ($scanStats['invalid'] ?? 0),
                    'already_redeemed' => (int) ($scanStats['already_redeemed'] ?? 0),
                    'void' => (int) ($scanStats['void'] ?? 0),
                    'outside_window' => (int) ($scanStats['outside_window'] ?? 0),
                ],
            ],
        ]);
    }

    public function scanLogs(Request $request, Meal $meal): JsonResponse
    {
        $query = $meal->scanLogs()->with(['scanner', 'ticket'])->latest('id');

        if ($request->filled('scan_result')) {
            $query->where('scan_result', $request->scan_result);
        }

        if ($request->filled('token')) {
            $query->where('token', 'like', '%' . trim($request->token) . '%');
        }

        $logs = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}