<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\InvoiceController;
use App\Http\Controllers\Api\Admin\ServiceTypeController;
use App\Http\Controllers\Api\Admin\ShopScheduleController;
use App\Http\Controllers\Api\Admin\StaffController;
use App\Http\Controllers\Api\Customer\AppointmentController as CustomerAppointmentController;
use App\Http\Controllers\Api\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Api\Customer\VehicleController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Staff\AppointmentController as StaffAppointmentController;
use App\Http\Controllers\Api\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Api\Staff\ServiceModificationController;
use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Storage route (public access for images)
Route::get('/storage/{path}', [StorageController::class, 'serve'])
    ->where('path', '.*')
    ->name('storage.serve');

// Protected routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware('throttle:6,1');
    Route::get('/user', [AuthController::class, 'user']);
    
    // Profile routes (all authenticated users)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
    
    // Admin routes
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        
        // Staff Management
        Route::apiResource('staff', StaffController::class);
        
        // Service Types Management
        Route::apiResource('services', ServiceTypeController::class);
        
        // Appointments Management
        Route::apiResource('appointments', AdminAppointmentController::class);
        Route::post('appointments/{appointment}/assign-staff', [AdminAppointmentController::class, 'assignStaff']);
        
        // Shop Schedule Management
        Route::get('schedule', [ShopScheduleController::class, 'index']);
        Route::post('schedule/staff', [ShopScheduleController::class, 'storeStaffSchedule']);
        Route::post('schedule/blackout', [ShopScheduleController::class, 'storeBlackoutDate']);
        Route::delete('schedule/blackout/{blackoutDate}', [ShopScheduleController::class, 'destroyBlackoutDate']);
        
        // Invoice Management
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/lock', [InvoiceController::class, 'lock']);
        Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve']);
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print']);
    });
    
    // Staff routes
    Route::middleware(['staff'])->prefix('staff')->group(function () {
        Route::get('/dashboard', [StaffDashboardController::class, 'index']);
        
        // Appointments
        Route::get('appointments', [StaffAppointmentController::class, 'index']);
        Route::get('appointments/{appointment}', [StaffAppointmentController::class, 'show']);
        Route::patch('appointments/{appointment}/status', [StaffAppointmentController::class, 'updateStatus']);
        Route::patch('appointments/{appointment}/notes', [StaffAppointmentController::class, 'updateNotes']);
        
        // Service Modifications
        Route::post('appointments/{appointment}/modifications', [ServiceModificationController::class, 'store']);
        Route::post('modifications/{modification}/approve', [ServiceModificationController::class, 'approve']);
        Route::post('modifications/{modification}/reject', [ServiceModificationController::class, 'reject']);
    });
    
    // Customer routes
    Route::middleware(['customer'])->prefix('customer')->group(function () {
        Route::get('/dashboard', [CustomerDashboardController::class, 'index']);
        
        // Vehicles
        Route::apiResource('vehicles', VehicleController::class);
        
        // Appointments
        Route::apiResource('appointments', CustomerAppointmentController::class);
        Route::get('appointments/{appointment}/available-slots', [CustomerAppointmentController::class, 'getAvailableSlots']);
    });
});

