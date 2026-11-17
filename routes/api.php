<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MobileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

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
        Route::get('me/balance', [MobileController::class, 'myBalance'])->name('me.balance');
    });
});
