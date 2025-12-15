<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'invoice_number',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'balance',
        'notes',
        'approved_at',
        'locked_at',
        'paid_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the appointment that owns the invoice.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the user who approved the invoice.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the invoice items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get customer approvals for this invoice.
     */
    public function customerApprovals(): HasMany
    {
        return $this->hasMany(CustomerApproval::class);
    }

    /**
     * Check if invoice is locked (finalized).
     */
    public function isLocked(): bool
    {
        return $this->status === 'locked' && $this->locked_at !== null;
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->balance <= 0;
    }

    /**
     * Check if invoice can be modified.
     */
    public function canBeModified(): bool
    {
        return !$this->isLocked() && !$this->isPaid() && in_array($this->status, ['draft', 'pending_approval', 'approved']);
    }

    /**
     * Calculate and update balance.
     */
    public function updateBalance(): void
    {
        $this->balance = $this->total_amount - $this->amount_paid;
        $this->save();
    }

    /**
     * Scope a query to only include locked invoices.
     */
    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    /**
     * Scope a query to only include unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', '!=', 'paid')->where('balance', '>', 0);
    }
}
