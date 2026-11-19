<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IsupGatewayController;
use App\Http\Controllers\Api\MobileController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Middleware\VerifyIsupGateway;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
    Route::get('public/info', [PublicController::class, 'info'])->middleware('throttle:60,1')->name('public.info');
    Route::post('public/leads', [PublicController::class, 'lead'])->middleware('throttle:5,1')->name('public.leads');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('lessons', [MobileController::class, 'lessons'])->name('lessons');
        Route::get('lessons/{lesson}', [MobileController::class, 'lesson'])->name('lessons.show');
        Route::post('lessons/{lesson}/attendance', [MobileController::class, 'attendance'])->name('lessons.attendance');
        Route::get('students', [MobileController::class, 'students'])->name('students');
        Route::get('students/{student}', [MobileController::class, 'student'])->name('students.show');
        Route::post('enrollments/{enrollment}/payments', [MobileController::class, 'payment'])->name('payments.store');
        Route::get('cashboxes', [MobileController::class, 'cashboxes'])->name('cashboxes');
        Route::put('me/locale', [MobileController::class, 'updateLocale'])->name('me.locale');
        Route::get('form-options', [MobileController::class, 'formOptions'])->name('form-options');
        Route::post('students', [MobileController::class, 'storeStudent'])->name('students.store');
        Route::post('leads', [MobileController::class, 'storeLead'])->name('leads.store');
        Route::get('me/balance', [MobileController::class, 'myBalance'])->name('me.balance');
        Route::get('me/students', [MobileController::class, 'myStudents'])->name('me.students');
    });
});

// Callbacks of the ISUP gateway (scripts/isup-gateway), shared token only.
Route::prefix('isup')->name('isup.')->middleware(VerifyIsupGateway::class)->group(function () {
    Route::get('device-key', [IsupGatewayController::class, 'deviceKey'])->name('device-key');
    Route::post('device-status', [IsupGatewayController::class, 'deviceStatus'])->name('device-status');
    Route::post('events', [IsupGatewayController::class, 'events'])->name('events');
});

// Live events posted by the terminals themselves (signed path, no token: devices cannot send headers).
Route::post('isup/push/{isupId}/{signature}', [IsupGatewayController::class, 'push'])->middleware('throttle:600,1')->name('isup.push');
