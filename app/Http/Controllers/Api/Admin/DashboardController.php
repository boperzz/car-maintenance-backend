<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    /**
     * Display the admin dashboard.
     */
    public function index()
    {
        $stats = [
            'total_appointments' => Appointment::count(),
            'pending_appointments' => Appointment::where('status', 'pending')->count(),
            'today_appointments' => Appointment::whereDate('appointment_date', today())->count(),
            'total_customers' => User::whereHas('role', function($q) {
                $q->where('name', 'user');
            })->count(),
            'total_staff' => User::whereHas('role', function($q) {
                $q->where('name', 'staff');
            })->count(),
            'total_vehicles' => Vehicle::count(),
            'total_services' => ServiceType::count(),
        ];

        $recentAppointments = Appointment::with(['user', 'vehicle', 'services', 'staff'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $appointmentsByStatus = Appointment::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $upcomingAppointments = Appointment::where('status', '!=', 'cancelled')
            ->whereBetween('appointment_date', [now(), now()->addDays(7)])
            ->with(['user', 'vehicle', 'services', 'staff'])
            ->orderBy('appointment_date')
            ->take(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_appointments' => $recentAppointments,
            'appointments_by_status' => $appointmentsByStatus,
            'upcoming_appointments' => $upcomingAppointments,
        ]);
    }
}

