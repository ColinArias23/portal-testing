<?php

use App\Http\Controllers\Api\Dtr\AdminDtrController;
use App\Http\Controllers\Api\Dtr\FaceRegistrationController;
use App\Http\Controllers\Api\Dtr\KioskController;
use App\Http\Controllers\Api\Dtr\MeController;
use App\Http\Controllers\Api\Dtr\SelfFaceRegistrationController;
use App\Http\Controllers\Api\Dtr\SignatureController;
use Illuminate\Support\Facades\Route;

Route::prefix('dtr')->name('dtr.')->group(function () {

    Route::middleware('dtr.network:kiosk')->prefix('kiosk')->group(function () {
        Route::post('recognize', [KioskController::class, 'recognize'])->middleware('throttle:20,1');
        Route::post('punch',     [KioskController::class, 'punch'])->middleware('throttle:30,1');
    });

    Route::middleware(['dtr.network:intranet', 'throttle:30,1'])->prefix('form')->group(function () {
        Route::get('employees/{employeeNumber}/dtr',       [AdminDtrController::class, 'employeeDtr']);
        Route::get('employees/{employeeNumber}/signature', [SignatureController::class, 'employee']);
        Route::get('signatures/verifier',                  [SignatureController::class, 'verifier']);
    });

    Route::middleware('dtr.network:intranet')->prefix('registration')->group(function () {
        Route::get('lookup', [SelfFaceRegistrationController::class, 'lookup']);
        Route::post('/',     [SelfFaceRegistrationController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'dtr.network:intranet'])->group(function () {

        Route::prefix('me')->group(function () {
            Route::get('status',  [MeController::class, 'status']);
            Route::post('verify', [MeController::class, 'verify'])->middleware('throttle:20,1');
            Route::post('punch',  [MeController::class, 'punch'])->middleware('throttle:30,1');
            Route::get('dtr',     [MeController::class, 'dtr']);
        });

        Route::middleware('permission:dtr.view|dtr.manage')->group(function () {
            Route::get('records', [AdminDtrController::class, 'records']);
            Route::get('employees/{employeeNumber}/dtr', [AdminDtrController::class, 'employeeDtr']);
            Route::get('attendances/{attendance}/image/{type}', [AdminDtrController::class, 'image'])
                ->whereIn('type', ['in', 'out']);
        });

        Route::middleware('permission:dtr.manage')->group(function () {
            Route::get('employees-lookup', [FaceRegistrationController::class, 'lookup']);
            Route::get('faces',            [FaceRegistrationController::class, 'index']);
            Route::post('faces',           [FaceRegistrationController::class, 'store'])->middleware('throttle:10,1');
            Route::delete('faces/{face}',  [FaceRegistrationController::class, 'destroy']);
        });
    });
});