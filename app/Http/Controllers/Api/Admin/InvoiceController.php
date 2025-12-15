<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    public function index(Request $request)
    {
        $query = Invoice::with(['appointment.user', 'appointment.vehicle', 'items']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('appointment', function ($q2) use ($search) {
                        $q2->where('job_order_number', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json(['invoices' => $invoices]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'appointment.user',
            'appointment.vehicle',
            'appointment.services',
            'items',
            'customerApprovals'
        ]);

        return response()->json(['invoice' => $invoice]);
    }

    public function lock(Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft invoices can be locked.'
            ], 422);
        }

        $invoice->update(['status' => 'locked']);
        
        return response()->json([
            'message' => 'Invoice locked successfully!',
            'invoice' => $invoice->load(['appointment.user', 'appointment.vehicle', 'items'])
        ]);
    }

    public function approve(Invoice $invoice)
    {
        if ($invoice->status !== 'locked') {
            return response()->json([
                'message' => 'Only locked invoices can be approved.'
            ], 422);
        }

        $invoice->update(['status' => 'approved']);
        
        return response()->json([
            'message' => 'Invoice approved successfully!',
            'invoice' => $invoice->load(['appointment.user', 'appointment.vehicle', 'items'])
        ]);
    }

    public function print(Invoice $invoice)
    {
        $invoice->load([
            'appointment.user',
            'appointment.vehicle',
            'appointment.services',
            'items'
        ]);

        // Return invoice data for printing (frontend will handle PDF generation)
        return response()->json(['invoice' => $invoice]);
    }
}

