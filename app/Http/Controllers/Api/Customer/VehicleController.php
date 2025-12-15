<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('customer');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $vehicles = Auth::user()->vehicles()->latest()->paginate(10);

        return response()->json([
            'vehicles' => $vehicles
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'license_plate' => ['required', 'string', 'max:20', 'unique:vehicles,license_plate'],
            'vin' => ['nullable', 'string', 'max:17', 'unique:vehicles,vin'],
            'color' => ['nullable', 'string', 'max:50'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
        ]);
        
        $data = $request->only(['make', 'model', 'year', 'license_plate', 'vin', 'color', 'mileage', 'notes']);

        // Handle picture upload
        if ($request->hasFile('picture')) {
            try {
                $directory = storage_path('app/public/vehicles');
                if (!file_exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }
                
                $file = $request->file('picture');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('vehicles', $filename, 'public');
                
                if ($path) {
                    $data['picture'] = $path;
                }
            } catch (\Exception $e) {
                \Log::error('Vehicle picture upload error: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Error uploading vehicle picture: ' . $e->getMessage()
                ], 500);
            }
        }

        $vehicle = Auth::user()->vehicles()->create($data);

        return response()->json([
            'message' => 'Vehicle added successfully',
            'vehicle' => $vehicle->load('appointments')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        $this->authorize('view', $vehicle);

        $vehicle->load('appointments.services');

        return response()->json([
            'vehicle' => $vehicle
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorize('update', $vehicle);

        $request->validate([
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'license_plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'license_plate')->ignore($vehicle->id)],
            'vin' => ['nullable', 'string', 'max:17', Rule::unique('vehicles', 'vin')->ignore($vehicle->id)],
            'color' => ['nullable', 'string', 'max:50'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
        ]);
        
        $data = $request->only(['make', 'model', 'year', 'license_plate', 'vin', 'color', 'mileage', 'notes']);

        // Handle picture upload
        if ($request->hasFile('picture')) {
            try {
                $directory = storage_path('app/public/vehicles');
                if (!file_exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }
                
                // Delete old picture if exists
                if ($vehicle->picture) {
                    Storage::disk('public')->delete($vehicle->picture);
                }
                
                $file = $request->file('picture');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('vehicles', $filename, 'public');
                
                if ($path) {
                    $data['picture'] = $path;
                }
            } catch (\Exception $e) {
                \Log::error('Vehicle picture upload error: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Error uploading vehicle picture: ' . $e->getMessage()
                ], 500);
            }
        }

        $vehicle->update($data);

        return response()->json([
            'message' => 'Vehicle updated successfully',
            'vehicle' => $vehicle->load('appointments')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        $this->authorize('delete', $vehicle);

        // Check if vehicle has active appointments
        $hasActiveAppointments = $vehicle->appointments()
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

        if ($hasActiveAppointments) {
            return response()->json([
                'message' => 'Cannot delete vehicle with active appointments. Please cancel or complete appointments first.'
            ], 422);
        }

        // Delete picture if exists
        if ($vehicle->picture) {
            Storage::disk('public')->delete($vehicle->picture);
        }

        $vehicle->delete();

        return response()->json([
            'message' => 'Vehicle deleted successfully'
        ]);
    }
}

