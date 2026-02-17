<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ManpowerOrgChartController;
use App\Http\Controllers\Api\ManpowerMappingController;

// Auth (public)
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
  Route::post('/auth/logout', [AuthController::class, 'logout']);
  Route::get('/auth/me', [AuthController::class, 'me']);

  // Manpower
  Route::get('/manpower-orgchart', [ManpowerOrgChartController::class, 'index'])
    ->middleware('permission:manpower.view');

  Route::get('/manpower-mapping', [ManpowerMappingController::class, 'index'])
    ->middleware('permission:manpower.view');
});
