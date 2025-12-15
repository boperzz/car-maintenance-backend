<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('role')
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'profile_picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);
        
        $data = $request->only(['name', 'last_name', 'middle_name', 'email']);

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            try {
                $directory = storage_path('app/public/profile_pictures');
                if (!file_exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }
                
                // Delete old profile picture if exists
                if ($user->profile_picture) {
                    Storage::disk('public')->delete($user->profile_picture);
                }
                
                $file = $request->file('profile_picture');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('profile_pictures', $filename, 'public');
                
                if ($path) {
                    $data['profile_picture'] = $path;
                }
            } catch (\Exception $e) {
                \Log::error('Profile picture upload error: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Error uploading profile picture: ' . $e->getMessage()
                ], 500);
            }
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->load('role')
        ]);
    }

    /**
     * Delete user account
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Delete all tokens
        $user->tokens()->delete();

        // Delete profile picture if exists
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully'
        ]);
    }
}

