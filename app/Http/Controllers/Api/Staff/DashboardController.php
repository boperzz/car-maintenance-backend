<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('staff');
    }

    /**
     * Display the staff dashboard.
     */
    public function index()
    {
        $user = Auth::user();

        $stats = [
            'assigned_appointments' => $user->assignedAppointments()->count(),
            'today_appointments' => $user->assignedAppointments()
                ->whereDate('appointment_date', today())
                ->count(),
            'pending_appointments' => $user->assignedAppointments()
                ->where('status', 'pending')
                ->count(),
            'in_progress_appointments' => $user->assignedAppointments()
                ->where('status', 'in_progress')
                ->count(),
        ];

        $todayAppointments = $user->assignedAppointments()
            ->whereDate('appointment_date', today())
            ->with(['user', 'vehicle', 'services'])
            ->orderBy('appointment_time')
            ->get();

        $upcomingAppointments = $user->assignedAppointments()
            ->where('status', '!=', 'cancelled')
            ->where('appointment_date', '>=', now())
            ->with(['user', 'vehicle', 'services'])
            ->orderBy('appointment_date')
            ->take(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'today_appointments' => $todayAppointments,
            'upcoming_appointments' => $upcomingAppointments,
        ]);
    }
}

