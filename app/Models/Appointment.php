<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'staff_id',
        'appointment_date',
        'end_time',
        'status',
        'notes',
        'staff_notes',
        'service_results',
        'total_price',
        'job_order_number',
        'payment_status',
        'payment_method',
        'amount_paid',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'datetime',
            'end_time' => 'datetime',
            'total_price' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the user (customer) that owns the appointment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vehicle for the appointment.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the staff assigned to the appointment.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Get the services for the appointment.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class, 'appointment_services')
            ->withPivot('price')
            ->withTimestamps();
    }

    /**
     * Get the appointment services pivot records.
     */
    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class);
    }

    /**
     * Scope a query to only include appointments with a specific status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include appointments for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('appointment_date', $date);
    }

    /**
     * Check if appointment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) 
            && $this->appointment_date->isFuture();
    }

    /**
     * Check if appointment can be rescheduled.
     */
    public function canBeRescheduled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) 
            && $this->appointment_date->isFuture();
    }

    /**
     * Get the invoice for this appointment.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Get service modifications for this appointment.
     */
    public function serviceModifications(): HasMany
    {
        return $this->hasMany(ServiceModification::class);
    }

    /**
     * Get customer approvals for this appointment.
     */
    public function customerApprovals(): HasMany
    {
        return $this->hasMany(CustomerApproval::class);
    }

    /**
     * Check if appointment is paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if appointment has pending payment.
     */
    public function hasPendingPayment(): bool
    {
        return in_array($this->payment_status, ['unpaid', 'partial']);
    }

    /**
     * Generate job order number.
     */
    public function generateJobOrderNumber(): string
    {
        if ($this->job_order_number) {
            return $this->job_order_number;
        }

        // Check if column exists before querying
        if (!\Illuminate\Support\Facades\Schema::hasColumn('appointments', 'job_order_number')) {
            // Fallback: return a basic job order number
            return 'JO-' . now()->format('Ymd') . '-0001';
        }

        $prefix = 'JO-';
        $date = now()->format('Ymd');
        $lastJob = self::where('job_order_number', 'like', $prefix . $date . '%')
            ->orderBy('job_order_number', 'desc')
            ->first();

        if ($lastJob) {
            $lastNumber = (int) substr($lastJob->job_order_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $jobOrderNumber = $prefix . $date . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        $this->update(['job_order_number' => $jobOrderNumber]);

        return $jobOrderNumber;
    }
}
