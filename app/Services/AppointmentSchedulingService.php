<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ServiceType;
use App\Models\ShopBlackoutDate;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AppointmentSchedulingService
{
    /**
     * Default buffer time between appointments (in minutes).
     */
    private const DEFAULT_BUFFER_MINUTES = 15;

    /**
     * Shop working hours (can be made configurable).
     */
    private const SHOP_OPEN_TIME = '08:00';
    private const SHOP_CLOSE_TIME = '18:00';

    /**
     * Check if a date/time slot is available.
     *
     * @param Carbon $appointmentDate
     * @param Collection $serviceTypes
     * @param int|null $staffId
     * @param int|null $excludeAppointmentId
     * @return array ['available' => bool, 'reason' => string|null]
     */
    public function checkAvailability(
        Carbon $appointmentDate,
        Collection $serviceTypes,
        ?int $staffId = null,
        ?int $excludeAppointmentId = null
    ): array {
        // Check if date is in the past
        if ($appointmentDate->isPast()) {
            return ['available' => false, 'reason' => 'Cannot book appointments in the past'];
        }

        // Check if date is a blackout date
        if (ShopBlackoutDate::isBlackedOut($appointmentDate)) {
            return ['available' => false, 'reason' => 'Shop is closed on this date'];
        }

        // Check shop working hours
        $appointmentTime = $appointmentDate->format('H:i');
        if ($appointmentTime < self::SHOP_OPEN_TIME || $appointmentTime >= self::SHOP_CLOSE_TIME) {
            return ['available' => false, 'reason' => 'Appointment time is outside shop hours'];
        }

        // Calculate total duration
        $totalDuration = $serviceTypes->sum('duration_minutes');
        $endTime = $appointmentDate->copy()->addMinutes($totalDuration + self::DEFAULT_BUFFER_MINUTES);
        $endTimeString = $endTime->format('H:i');

        if ($endTimeString > self::SHOP_CLOSE_TIME) {
            return ['available' => false, 'reason' => 'Appointment would extend beyond shop closing time'];
        }

        // Check staff availability if staff is specified
        if ($staffId) {
            $staffAvailability = $this->checkStaffAvailability(
                $appointmentDate,
                $endTime,
                $staffId,
                $excludeAppointmentId
            );

            if (!$staffAvailability['available']) {
                return $staffAvailability;
            }
        } else {
            // Check general availability (any staff member)
            $hasAvailableStaff = $this->hasAvailableStaff($appointmentDate, $endTime);

            if (!$hasAvailableStaff) {
                return ['available' => false, 'reason' => 'No staff available for this time slot'];
            }
        }

        // Check for overlapping appointments
        $overlapping = $this->checkOverlappingAppointments(
            $appointmentDate,
            $endTime,
            $staffId,
            $excludeAppointmentId
        );

        if ($overlapping) {
            return ['available' => false, 'reason' => 'Time slot conflicts with existing appointment'];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * Check if staff is available for the time slot.
     */
    private function checkStaffAvailability(
        Carbon $startTime,
        Carbon $endTime,
        int $staffId,
        ?int $excludeAppointmentId = null
    ): array {
        $dayOfWeek = strtolower($startTime->format('l'));

        // Check staff schedule
        $schedule = StaffSchedule::where('staff_id', $staffId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (!$schedule) {
            return ['available' => false, 'reason' => 'Staff member is not scheduled for this day'];
        }

        $scheduleStartTime = is_string($schedule->start_time) ? $schedule->start_time : $schedule->start_time->format('H:i:s');
        $scheduleEndTime = is_string($schedule->end_time) ? $schedule->end_time : $schedule->end_time->format('H:i:s');
        $scheduleStart = Carbon::parse($startTime->format('Y-m-d') . ' ' . $scheduleStartTime);
        $scheduleEnd = Carbon::parse($startTime->format('Y-m-d') . ' ' . $scheduleEndTime);

        if ($startTime->lt($scheduleStart) || $endTime->gt($scheduleEnd)) {
            return ['available' => false, 'reason' => 'Appointment time is outside staff working hours'];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * Check if any staff member is available.
     */
    private function hasAvailableStaff(Carbon $startTime, Carbon $endTime): bool
    {
        $dayOfWeek = strtolower($startTime->format('l'));

        $availableStaff = StaffSchedule::where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->whereHas('staff', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', 'staff');
                });
            })
            ->get();

        foreach ($availableStaff as $schedule) {
            $scheduleStartTime = is_string($schedule->start_time) ? $schedule->start_time : $schedule->start_time->format('H:i:s');
            $scheduleEndTime = is_string($schedule->end_time) ? $schedule->end_time : $schedule->end_time->format('H:i:s');
            $scheduleStart = Carbon::parse($startTime->format('Y-m-d') . ' ' . $scheduleStartTime);
            $scheduleEnd = Carbon::parse($startTime->format('Y-m-d') . ' ' . $scheduleEndTime);

            if ($startTime->gte($scheduleStart) && $endTime->lte($scheduleEnd)) {
                // Check if this staff member has overlapping appointments
                $overlapping = Appointment::where('staff_id', $schedule->staff_id)
                    ->where('status', '!=', 'cancelled')
                    ->where(function ($query) use ($startTime, $endTime) {
                        $query->whereBetween('appointment_date', [$startTime, $endTime])
                            ->orWhereBetween('end_time', [$startTime, $endTime])
                            ->orWhere(function ($q) use ($startTime, $endTime) {
                                $q->where('appointment_date', '<=', $startTime)
                                    ->where('end_time', '>=', $endTime);
                            });
                    })
                    ->exists();

                if (!$overlapping) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check for overlapping appointments.
     */
    private function checkOverlappingAppointments(
        Carbon $startTime,
        Carbon $endTime,
        ?int $staffId = null,
        ?int $excludeAppointmentId = null
    ): bool {
        $query = Appointment::where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startTime, $endTime) {
                // Check if new appointment overlaps with existing ones
                $q->whereBetween('appointment_date', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($query) use ($startTime, $endTime) {
                        $query->where('appointment_date', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($staffId) {
            $query->where('staff_id', $staffId);
        }

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->exists();
    }

    /**
     * Calculate appointment end time based on services.
     */
    public function calculateEndTime(Carbon $startTime, Collection $serviceTypes): Carbon
    {
        $totalDuration = $serviceTypes->sum('duration_minutes');
        return $startTime->copy()->addMinutes($totalDuration);
    }

    /**
     * Calculate total price for services.
     */
    public function calculateTotalPrice(Collection $serviceTypes): float
    {
        return $serviceTypes->sum('price');
    }

    /**
     * Create an appointment with conflict checking.
     *
     * @param array $data
     * @return Appointment
     * @throws \Exception
     */
    public function createAppointment(array $data): Appointment
    {
        $appointmentDate = Carbon::parse($data['appointment_date']);
        $serviceTypes = ServiceType::whereIn('id', $data['service_ids'])->get();

        if ($serviceTypes->count() !== count($data['service_ids'])) {
            throw new \Exception('One or more service types not found');
        }

        // Check availability
        $availability = $this->checkAvailability(
            $appointmentDate,
            $serviceTypes,
            $data['staff_id'] ?? null
        );

        if (!$availability['available']) {
            throw new \Exception($availability['reason']);
        }

        // Calculate end time and total price
        $endTime = $this->calculateEndTime($appointmentDate, $serviceTypes);
        $totalPrice = $this->calculateTotalPrice($serviceTypes);

        DB::beginTransaction();
        try {
            // Generate job order number
            $prefix = 'JO-';
            $date = now()->format('Ymd');
            
            // Check if job_order_number column exists before querying
            $jobOrderNumber = null;
            if (Schema::hasColumn('appointments', 'job_order_number')) {
                $lastJob = Appointment::where('job_order_number', 'like', $prefix . $date . '%')
                    ->orderBy('job_order_number', 'desc')
                    ->first();

                if ($lastJob) {
                    $lastNumber = (int) substr($lastJob->job_order_number, -4);
                    $newNumber = $lastNumber + 1;
                } else {
                    $newNumber = 1;
                }

                $jobOrderNumber = $prefix . $date . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
            } else {
                // Fallback: use appointment ID if column doesn't exist yet
                $jobOrderNumber = $prefix . $date . '-' . str_pad(1, 4, '0', STR_PAD_LEFT);
            }

            $appointment = Appointment::create([
                'user_id' => $data['user_id'],
                'vehicle_id' => $data['vehicle_id'],
                'staff_id' => $data['staff_id'] ?? null,
                'appointment_date' => $appointmentDate,
                'end_time' => $endTime,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'total_price' => $totalPrice,
                'job_order_number' => $jobOrderNumber,
                'payment_status' => 'unpaid',
                'payment_method' => 'POS', // Default to POS
                'amount_paid' => 0,
            ]);

            // Attach services
            foreach ($serviceTypes as $serviceType) {
                $appointment->services()->attach($serviceType->id, [
                    'price' => $serviceType->price,
                ]);
            }

            DB::commit();
            return $appointment->fresh(['services', 'user', 'vehicle', 'staff']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create appointment: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get available time slots for a given date.
     *
     * @param Carbon $date
     * @param Collection $serviceTypes
     * @param int|null $staffId
     * @return array
     */
    public function getAvailableTimeSlots(Carbon $date, Collection $serviceTypes, ?int $staffId = null): array
    {
        $slots = [];
        $totalDuration = $serviceTypes->sum('duration_minutes');
        $slotDuration = 30; // 30-minute slots
        $buffer = self::DEFAULT_BUFFER_MINUTES;

        $start = Carbon::parse($date->format('Y-m-d') . ' ' . self::SHOP_OPEN_TIME);
        $end = Carbon::parse($date->format('Y-m-d') . ' ' . self::SHOP_CLOSE_TIME);

        $current = $start->copy();

        while ($current->copy()->addMinutes($totalDuration + $buffer)->lte($end)) {
            $availability = $this->checkAvailability($current, $serviceTypes, $staffId);

            if ($availability['available']) {
                $slots[] = [
                    'time' => $current->format('H:i'),
                    'datetime' => $current->toDateTimeString(),
                ];
            }

            $current->addMinutes($slotDuration);
        }

        return $slots;
    }

    /**
     * Auto-assign staff to appointment.
     */
    public function autoAssignStaff(Carbon $appointmentDate, Carbon $endTime): ?int
    {
        $dayOfWeek = strtolower($appointmentDate->format('l'));

        $availableStaff = StaffSchedule::where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->whereHas('staff', function ($query) {
                $query->whereHas('role', function ($q) {
                    $q->where('name', 'staff');
                });
            })
            ->get();

        foreach ($availableStaff as $schedule) {
            $scheduleStartTime = is_string($schedule->start_time) ? $schedule->start_time : $schedule->start_time->format('H:i:s');
            $scheduleEndTime = is_string($schedule->end_time) ? $schedule->end_time : $schedule->end_time->format('H:i:s');
            $scheduleStart = Carbon::parse($appointmentDate->format('Y-m-d') . ' ' . $scheduleStartTime);
            $scheduleEnd = Carbon::parse($appointmentDate->format('Y-m-d') . ' ' . $scheduleEndTime);

            if ($appointmentDate->gte($scheduleStart) && $endTime->lte($scheduleEnd)) {
                // Check if staff has overlapping appointments
                $overlapping = Appointment::where('staff_id', $schedule->staff_id)
                    ->where('status', '!=', 'cancelled')
                    ->where(function ($query) use ($appointmentDate, $endTime) {
                        $query->whereBetween('appointment_date', [$appointmentDate, $endTime])
                            ->orWhereBetween('end_time', [$appointmentDate, $endTime])
                            ->orWhere(function ($q) use ($appointmentDate, $endTime) {
                                $q->where('appointment_date', '<=', $appointmentDate)
                                    ->where('end_time', '>=', $endTime);
                            });
                    })
                    ->exists();

                if (!$overlapping) {
                    return $schedule->staff_id;
                }
            }
        }

        return null;
    }
}

