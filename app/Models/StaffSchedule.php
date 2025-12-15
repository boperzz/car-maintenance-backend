<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    /**
     * Get start time as Carbon instance.
     */
    public function getStartTimeAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }
        return is_string($value) ? \Carbon\Carbon::parse($value) : $value;
    }

    /**
     * Get end time as Carbon instance.
     */
    public function getEndTimeAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }
        return is_string($value) ? \Carbon\Carbon::parse($value) : $value;
    }

    /**
     * Get the staff member.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Scope a query to only include available schedules.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope a query for a specific day of week.
     */
    public function scopeForDay($query, string $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
}
