<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    public function index()
    {
        $services = ServiceType::latest()->paginate(15);
        return response()->json(['services' => $services]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);
        
        $service = ServiceType::create($request->only(['name', 'description', 'price', 'duration_minutes', 'is_active']));
        return response()->json([
            'message' => 'Service type created!',
            'service' => $service
        ], 201);
    }

    public function show(ServiceType $service)
    {
        return response()->json(['service' => $service]);
    }

    public function update(Request $request, ServiceType $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);
        
        $service->update($request->only(['name', 'description', 'price', 'duration_minutes', 'is_active']));
        return response()->json([
            'message' => 'Service type updated!',
            'service' => $service
        ]);
    }

    public function destroy(ServiceType $service)
    {
        if ($service->appointments()->whereIn('status', ['pending', 'confirmed', 'in_progress'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete service with active appointments.'
            ], 422);
        }
        $service->delete();
        return response()->json(['message' => 'Service type deleted!']);
    }
}

