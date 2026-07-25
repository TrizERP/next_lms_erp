<?php

use App\Http\Controllers\api\HostelSetupApiController;
use Illuminate\Support\Facades\Route;

Route::get('hostel-setup/{module}', [HostelSetupApiController::class, 'index']);
Route::post('hostel-setup/{module}', [HostelSetupApiController::class, 'store']);
Route::match(['put', 'patch'], 'hostel-setup/{module}/{id}', [HostelSetupApiController::class, 'update']);
Route::delete('hostel-setup/{module}/{id}', [HostelSetupApiController::class, 'destroy']);
