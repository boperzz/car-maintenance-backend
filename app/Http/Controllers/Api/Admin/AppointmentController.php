<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AppointmentStatusUpdated;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AppointmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    public function index(Request $request)
    {
        $query = Appointment::with(['user', 'vehicle', 'services', 'staff']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('appointment_date', $request->date);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')->paginate(20);
        $staffMembers = User::whereHas('role', fn($q) => $q->where('name', 'staff'))->get();

        return response()->json([
            'appointments' => $appointments,
            'staff_members' => $staffMembers
        ]);
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['user', 'vehicle', 'services', 'staff']);
        $staffMembers = User::whereHas('role', fn($q) => $q->where('name', 'staff'))->get();
        
        return response()->json([
            'appointment' => $appointment,
            'staff_members' => $staffMembers
        ]);
    }

    public function assignStaff(Request $request, Appointment $appointment)
    {
        $request->validate(['staff_id' => 'required|exists:users,id']);
        
        $appointment->update(['staff_id' => $request->staff_id]);
        
        return response()->json([
            'message' => 'Staff assigned successfully!',
            'appointment' => $appointment->load(['user', 'vehicle', 'services', 'staff'])
        ]);
    }
}

