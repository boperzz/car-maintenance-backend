<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'invoice_id',
        'approval_type',
        'status',
        'request_details',
        'original_amount',
        'new_amount',
        'customer_notes',
        'requested_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * Get the appointment for this approval.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the invoice for this approval.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Check if approval is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if approval is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if approval is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Scope a query to only include pending approvals.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
