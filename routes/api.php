<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TripController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('auth')->group(function(){
    Route::post('login', [AuthController::class, 'login'])->middleware('guest');
    Route::delete('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});


Route::get('trips', [TripController::class, 'index']);
Route::get('trips/{trip}', [TripController::class, 'show']);

Route::middleware('auth:sanctum')->group(function(){
    Route::post('trips/{trip}', [TripController::class, 'store']);
});
