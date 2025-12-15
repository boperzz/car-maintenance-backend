<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Mail\AppointmentStatusUpdated;
use App\Models\Appointment;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AppointmentController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('staff');
    }

    public function index(Request $request)
    {
        $staff = Auth::user();
        
        $query = $staff->assignedAppointments()
            ->with(['user', 'vehicle', 'services']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('appointment_date', $request->date);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')->paginate(15);

        return response()->json(['appointments' => $appointments]);
    }

    public function show(Appointment $appointment)
    {
        if ($appointment->staff_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only view your assigned appointments.'
            ], 403);
        }

        $appointment->load([
            'user',
            'vehicle',
            'services',
            'staff',
            'invoice.items',
            'serviceModifications.modifiedBy',
            'customerApprovals'
        ]);

        return response()->json(['appointment' => $appointment]);
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        if ($appointment->staff_id !== Auth::id()) {
            return response()->json([
                'message' => 'You can only update your assigned appointments.'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:confirmed,in_progress,completed,cancelled'
        ]);

        $oldStatus = $appointment->status;
        $appointment->update(['status' => $request->status]);

        // Auto-generate invoice when completed
        if ($request->status === 'completed' && !$appointment->invoice) {
            try {
                $this->invoiceService->createFromAppointment($appointment);
            } catch (\Exception $e) {
                \Log::error('Failed to generate invoice: ' . $e->getMessage());
            }
        }

        // Send status update email
        try {
            Mail::to($appointment->user->email)->send(
                new AppointmentStatusUpdated($appointment, $oldStatus)
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send status update email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Appointment status updated successfully!',
            'appointment' => $appointment->load(['user', 'vehicle', 'services', 'staff'])
        ]);
    }

    public function updateNotes(Request $request, Appointment $appointment)
    {
        if ($appointment->staff_id !== Auth::id()) {
            return response()->json([
                'message' => 'You can only update your assigned appointments.'
            ], 403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:5000'
        ]);

        $appointment->update(['notes' => $request->notes]);

        return response()->json([
            'message' => 'Notes updated successfully!',
            'appointment' => $appointment->load(['user', 'vehicle', 'services', 'staff'])
        ]);
    }
}

