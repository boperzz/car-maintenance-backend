<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('customer');
    }

    /**
     * Display the customer dashboard.
     */
    public function index()
    {
        $user = Auth::user();

        $stats = [
            'vehicles_count' => $user->vehicles()->count(),
            'appointments_count' => $user->appointments()->count(),
            'pending_appointments' => $user->appointments()->where('status', 'pending')->count(),
            'upcoming_appointments' => $user->appointments()
                ->where('status', '!=', 'cancelled')
                ->where('appointment_date', '>=', now())
                ->count(),
        ];

        $recentAppointments = $user->appointments()
            ->with(['vehicle', 'services', 'staff'])
            ->latest('appointment_date')
            ->take(5)
            ->get();

        $recentVehicles = $user->vehicles()
            ->latest()
            ->take(3)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_appointments' => $recentAppointments,
            'recent_vehicles' => $recentVehicles,
        ]);
    }
}

