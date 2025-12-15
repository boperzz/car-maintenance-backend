<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    public function index()
    {
        $staff = User::whereHas('role', fn($q) => $q->where('name', 'staff'))->paginate(15);
        return response()->json(['staff' => $staff]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username', 'alpha_dash'],
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);
        
        $staffRole = Role::where('name', 'staff')->first();
        
        $data = [
            'username' => $request->username,
            'name' => $request->name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $staffRole->id,
        ];

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            try {
                $directory = storage_path('app/public/profile_pictures');
                if (!file_exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }
                
                $file = $request->file('profile_picture');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('profile_pictures', $filename, 'public');
                
                if ($path) {
                    $data['profile_picture'] = $path;
                }
            } catch (\Exception $e) {
                \Log::error('Staff profile picture upload error: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Error uploading profile picture: ' . $e->getMessage()
                ], 500);
            }
        }

        $staff = User::create($data);

        return response()->json([
            'message' => 'Staff member added successfully!',
            'staff' => $staff->load('role')
        ], 201);
    }

    public function show(User $staff)
    {
        if (!$staff->isStaff()) {
            return response()->json(['message' => 'User is not a staff member'], 404);
        }
        return response()->json(['staff' => $staff->load('role')]);
    }

    public function update(Request $request, User $staff)
    {
        if (!$staff->isStaff()) {
            return response()->json(['message' => 'User is not a staff member'], 404);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $staff->id,
            'password' => 'sometimes|nullable|min:8',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only(['name', 'last_name', 'middle_name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            try {
                if ($staff->profile_picture) {
                    Storage::disk('public')->delete($staff->profile_picture);
                }
                
                $file = $request->file('profile_picture');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('profile_pictures', $filename, 'public');
                
                if ($path) {
                    $data['profile_picture'] = $path;
                }
            } catch (\Exception $e) {
                \Log::error('Staff profile picture upload error: ' . $e->getMessage());
            }
        }

        $staff->update($data);

        return response()->json([
            'message' => 'Staff member updated successfully!',
            'staff' => $staff->load('role')
        ]);
    }

    public function destroy(User $staff)
    {
        if (!$staff->isStaff()) {
            return response()->json(['message' => 'User is not a staff member'], 404);
        }

        if ($staff->assignedAppointments()->whereIn('status', ['pending', 'confirmed', 'in_progress'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete staff member with active appointments.'
            ], 422);
        }

        if ($staff->profile_picture) {
            Storage::disk('public')->delete($staff->profile_picture);
        }

        $staff->delete();

        return response()->json(['message' => 'Staff member deleted successfully!']);
    }
}

