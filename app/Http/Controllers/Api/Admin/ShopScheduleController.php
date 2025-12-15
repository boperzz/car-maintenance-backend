<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopBlackoutDate;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Http\Request;

class ShopScheduleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    public function index()
    {
        $staffMembers = User::whereHas('role', fn($q) => $q->where('name', 'staff'))->with('staffSchedules')->get();
        $blackoutDates = ShopBlackoutDate::where('date', '>=', now())->orderBy('date')->get();
        
        return response()->json([
            'staff_members' => $staffMembers,
            'blackout_dates' => $blackoutDates
        ]);
    }

    public function storeStaffSchedule(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:users,id',
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        $schedule = StaffSchedule::updateOrCreate(
            ['staff_id' => $request->staff_id, 'day_of_week' => $request->day_of_week],
            ['start_time' => $request->start_time, 'end_time' => $request->end_time, 'is_available' => true]
        );

        return response()->json([
            'message' => 'Staff schedule updated!',
            'schedule' => $schedule
        ]);
    }

    public function storeBlackoutDate(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'reason' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $blackoutDate = ShopBlackoutDate::create($request->all());
        
        return response()->json([
            'message' => 'Blackout date added!',
            'blackout_date' => $blackoutDate
        ], 201);
    }

    public function destroyBlackoutDate(ShopBlackoutDate $blackoutDate)
    {
        $blackoutDate->delete();
        return response()->json(['message' => 'Blackout date removed!']);
    }
}

