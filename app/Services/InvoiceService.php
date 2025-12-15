<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ServiceModification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    /**
     * Generate invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-';
        $date = now()->format('Ymd');
        $lastInvoice = Invoice::where('invoice_number', 'like', $prefix . $date . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $date . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create initial invoice from appointment.
     */
    public function createFromAppointment(Appointment $appointment): Invoice
    {
        DB::beginTransaction();
        try {
            $invoice = Invoice::create([
                'appointment_id' => $appointment->id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'status' => 'draft',
                'subtotal' => $appointment->total_price,
                'tax_amount' => 0, // Can be calculated based on tax rate
                'discount_amount' => 0,
                'total_amount' => $appointment->total_price,
                'amount_paid' => 0,
                'balance' => $appointment->total_price,
            ]);

            // Add invoice items from appointment services
            foreach ($appointment->services as $service) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'service',
                    'item_name' => $service->name,
                    'description' => $service->description,
                    'quantity' => 1,
                    'unit_price' => $service->pivot->price,
                    'total_price' => $service->pivot->price,
                    'service_type_id' => $service->id,
                ]);
            }

            DB::commit();
            return $invoice->fresh(['items']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create invoice: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update invoice with service modifications.
     */
    public function updateWithModifications(Invoice $invoice, array $modifications): Invoice
    {
        if (!$invoice->canBeModified()) {
            throw new \Exception('Invoice cannot be modified. It is locked or paid.');
        }

        DB::beginTransaction();
        try {
            $subtotal = $invoice->subtotal;

            foreach ($modifications as $modification) {
                if ($modification->isApproved()) {
                    // Add approved modification to invoice
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'item_type' => $modification->modification_type,
                        'item_name' => $modification->item_name,
                        'description' => $modification->description,
                        'quantity' => $modification->quantity,
                        'unit_price' => $modification->unit_price,
                        'total_price' => $modification->total_price,
                        'service_type_id' => $modification->service_type_id,
                    ]);

                    $subtotal += $modification->total_price;
                }
            }

            // Recalculate totals
            $invoice->subtotal = $subtotal;
            $invoice->total_amount = $subtotal + $invoice->tax_amount - $invoice->discount_amount;
            $invoice->balance = $invoice->total_amount - $invoice->amount_paid;
            $invoice->save();

            DB::commit();
            return $invoice->fresh(['items']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update invoice with modifications: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lock invoice (finalize it).
     */
    public function lockInvoice(Invoice $invoice, int $approvedBy): Invoice
    {
        if ($invoice->isLocked()) {
            throw new \Exception('Invoice is already locked.');
        }

        if ($invoice->status !== 'approved') {
            throw new \Exception('Invoice must be approved before locking.');
        }

        $invoice->update([
            'status' => 'locked',
            'locked_at' => now(),
            'approved_by' => $approvedBy,
        ]);

        return $invoice;
    }

    /**
     * Calculate invoice totals.
     */
    public function calculateTotals(Invoice $invoice): array
    {
        $subtotal = $invoice->items()->sum('total_price');
        $taxAmount = $subtotal * 0.10; // 10% tax (can be configurable)
        $discountAmount = $invoice->discount_amount;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        $balance = $totalAmount - $invoice->amount_paid;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'balance' => $balance,
        ];
    }

    /**
     * Recalculate and update invoice totals.
     */
    public function recalculateTotals(Invoice $invoice): Invoice
    {
        $totals = $this->calculateTotals($invoice);

        $invoice->update($totals);

        return $invoice->fresh();
    }
}
