<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Models\ServiceType;
use App\Services\AppointmentSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(
        private AppointmentSchedulingService $schedulingService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('customer');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $appointments = Appointment::where('user_id', Auth::id())
            ->with(['vehicle', 'services', 'staff'])
            ->orderBy('appointment_date', 'desc')
            ->paginate(15);

        return response()->json([
            'appointments' => $appointments
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id', Rule::exists('vehicles', 'id')->where('user_id', Auth::id())],
            'appointment_date' => ['required', 'date', 'after:now'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:service_types,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        
        try {
            $appointment = $this->schedulingService->createAppointment([
                'user_id' => Auth::id(),
                'vehicle_id' => $request->vehicle_id,
                'appointment_date' => $request->appointment_date,
                'service_ids' => $request->service_ids,
                'notes' => $request->notes,
            ]);

            // Send confirmation email
            try {
                Mail::to($appointment->user->email)->send(new AppointmentConfirmed($appointment));
            } catch (\Exception $e) {
                \Log::error('Failed to send appointment confirmation email: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Appointment booked successfully! A confirmation email has been sent.',
                'appointment' => $appointment->load(['vehicle', 'services', 'staff', 'user'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Appointment $appointment)
    {
        $this->authorize('view', $appointment);

        $appointment->load(['vehicle', 'services', 'staff', 'user']);

        return response()->json([
            'appointment' => $appointment
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        if (!$appointment->canBeRescheduled()) {
            return response()->json([
                'message' => 'This appointment cannot be rescheduled.'
            ], 422);
        }

        $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id', Rule::exists('vehicles', 'id')->where('user_id', Auth::id())],
            'appointment_date' => ['required', 'date', 'after:now'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:service_types,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $oldStatus = $appointment->status;
            $appointmentDate = Carbon::parse($request->appointment_date);
            $serviceTypes = ServiceType::whereIn('id', $request->service_ids)->get();

            // Check availability (excluding current appointment)
            $availability = $this->schedulingService->checkAvailability(
                $appointmentDate,
                $serviceTypes,
                $appointment->staff_id,
                $appointment->id
            );

            if (!$availability['available']) {
                return response()->json([
                    'message' => $availability['reason']
                ], 422);
            }

            // Calculate new end time and total price
            $endTime = $this->schedulingService->calculateEndTime($appointmentDate, $serviceTypes);
            $totalPrice = $this->schedulingService->calculateTotalPrice($serviceTypes);

            $appointment->update([
                'appointment_date' => $appointmentDate,
                'end_time' => $endTime,
                'total_price' => $totalPrice,
                'notes' => $request->notes,
            ]);

            // Update services
            $appointment->services()->detach();
            foreach ($serviceTypes as $serviceType) {
                $appointment->services()->attach($serviceType->id, [
                    'price' => $serviceType->price,
                ]);
            }

            // Send status update email if status changed
            if ($appointment->status !== $oldStatus) {
                try {
                    Mail::to($appointment->user->email)->send(
                        new \App\Mail\AppointmentStatusUpdated($appointment, $oldStatus)
                    );
                } catch (\Exception $e) {
                    \Log::error('Failed to send status update email: ' . $e->getMessage());
                }
            }

            return response()->json([
                'message' => 'Appointment updated successfully!',
                'appointment' => $appointment->load(['vehicle', 'services', 'staff', 'user'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Appointment $appointment)
    {
        $this->authorize('delete', $appointment);

        if (!$appointment->canBeCancelled()) {
            return response()->json([
                'message' => 'This appointment cannot be cancelled.'
            ], 422);
        }

        $appointment->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Appointment cancelled successfully.'
        ]);
    }

    /**
     * Get available time slots for a date.
     */
    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date', 'after:today'],
            'service_ids' => ['required', 'array'],
            'service_ids.*' => ['exists:service_types,id'],
        ]);

        $date = Carbon::parse($request->date);
        $serviceTypes = ServiceType::whereIn('id', $request->service_ids)->get();

        $slots = $this->schedulingService->getAvailableTimeSlots($date, $serviceTypes);

        return response()->json(['slots' => $slots]);
    }
}

