<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ServiceModification;
use App\Models\CustomerApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceModificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('staff');
    }

    public function store(Request $request, Appointment $appointment)
    {
        if ($appointment->staff_id !== Auth::id() && !Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'You can only modify your assigned appointments.'
            ], 403);
        }

        if (!in_array($appointment->status, ['in_progress', 'confirmed'])) {
            return response()->json([
                'message' => 'Can only add modifications to appointments in progress.'
            ], 422);
        }

        $request->validate([
            'modification_type' => ['required', 'in:add_service,remove_service,add_labor,add_part,adjust_price,discount'],
            'item_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            $totalPrice = $request->quantity * $request->unit_price;

            $modification = ServiceModification::create([
                'appointment_id' => $appointment->id,
                'modified_by' => Auth::id(),
                'modification_type' => $request->modification_type,
                'item_name' => $request->item_name,
                'description' => $request->description,
                'quantity' => $request->quantity,
                'unit_price' => $request->unit_price,
                'total_price' => $totalPrice,
                'service_type_id' => $request->service_type_id,
                'status' => 'pending_approval',
                'reason' => $request->reason,
            ]);

            $originalAmount = $appointment->total_price;
            $newAmount = $originalAmount + $totalPrice;

            CustomerApproval::create([
                'appointment_id' => $appointment->id,
                'approval_type' => 'service_modification',
                'status' => 'pending',
                'request_details' => "Service modification: {$request->item_name} - {$request->reason}",
                'original_amount' => $originalAmount,
                'new_amount' => $newAmount,
                'requested_at' => now(),
            ]);

            if ($appointment->status !== 'waiting_for_approval') {
                $appointment->update(['status' => 'waiting_for_approval']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Service modification added. Waiting for customer approval.',
                'modification' => $modification->load('modifiedBy')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add modification: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve(ServiceModification $modification)
    {
        if (!$modification->isPendingApproval()) {
            return response()->json([
                'message' => 'Modification is not pending approval.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $modification->update(['status' => 'approved']);

            $appointment = $modification->appointment;
            $appointment->total_price += $modification->total_price;
            $appointment->save();

            $approval = CustomerApproval::where('appointment_id', $appointment->id)
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($approval) {
                $approval->update([
                    'status' => 'approved',
                    'responded_at' => now(),
                ]);
            }

            $pendingApprovals = CustomerApproval::where('appointment_id', $appointment->id)
                ->where('status', 'pending')
                ->count();

            if ($pendingApprovals === 0) {
                $appointment->update(['status' => 'in_progress']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Modification approved successfully!',
                'modification' => $modification->load('modifiedBy')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to approve modification: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject(ServiceModification $modification, Request $request)
    {
        if (!$modification->isPendingApproval()) {
            return response()->json([
                'message' => 'Modification is not pending approval.'
            ], 422);
        }

        $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $modification->update([
            'status' => 'rejected',
            'reason' => $modification->reason . ' [REJECTED: ' . ($request->rejection_reason ?? 'No reason provided') . ']',
        ]);

        $approval = CustomerApproval::where('appointment_id', $modification->appointment_id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($approval) {
            $approval->update([
                'status' => 'rejected',
                'responded_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Modification rejected.',
            'modification' => $modification->load('modifiedBy')
        ]);
    }
}

