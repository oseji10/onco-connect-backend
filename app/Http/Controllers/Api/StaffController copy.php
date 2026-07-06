<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;

class StaffController extends Controller
{
    public function index()
    {
        $staff = User::with('user_role')
            // ->whereHas('role', function ($q) {
            //     $q->whereIn('name', ['CL', 'SUBCL', 'WIMA_CORE']);
            // })
            ->whereHas('user_role', function ($q) {
    $q->where('isStaff', true);
})
            ->get()
            ->map(function ($user) {

                // Example: pull portfolio if CL
                $portfolio = null;

                if ($user->user_role->roleName === 'CL') {
                    $cl = \App\Models\CL::where('userId', $user->id)->first();
                    $portfolio = $cl?->lga;
                }

                return [
                    'id' => $user->id,
                    'name' => $user->firstName . ' ' . $user->lastName,
                    'role' => strtoupper($user->user_role->roleName),
                    'image' => $user->photo ?? null,
                    'portfolio' => $portfolio,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $staff
        ]);
    }
}