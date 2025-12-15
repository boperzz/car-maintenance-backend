<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopBlackoutDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'reason',
        'description',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    /**
     * Scope a query to check if a date is blacked out.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Check if a date is blacked out (including recurring).
     */
    public static function isBlackedOut($date): bool
    {
        $dateString = $date instanceof \DateTime ? $date->format('Y-m-d') : $date;
        
        // Check exact date match
        if (static::where('date', $dateString)->exists()) {
            return true;
        }

        // Check recurring dates (same month and day)
        $month = date('m', strtotime($dateString));
        $day = date('d', strtotime($dateString));
        
        return static::where('is_recurring', true)
            ->whereMonth('date', $month)
            ->whereDay('date', $day)
            ->exists();
    }
}
