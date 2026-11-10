<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('destinations', DestinationController::class)->except('show');

    Route::post('vehicles/{id}/restore', [VehicleController::class, 'restore']);
    Route::apiResource('vehicles', VehicleController::class);

    Route::post('drivers/{id}/restore', [DriverController::class, 'restore']);
    Route::apiResource('drivers', DriverController::class);

    Route::post('users/{id}/restore', [UserController::class, 'restore']);
    Route::apiResource('users', UserController::class);

    // must come before the {booking} routes
    Route::get('bookings/availability', [BookingController::class, 'availability']);
    Route::post('bookings/{booking}/approve', [BookingController::class, 'approve']);
    Route::post('bookings/{booking}/reject', [BookingController::class, 'reject']);
    Route::post('bookings/{booking}/start', [BookingController::class, 'start']);
    Route::post('bookings/{booking}/complete', [BookingController::class, 'complete']);
    Route::apiResource('bookings', BookingController::class);
});