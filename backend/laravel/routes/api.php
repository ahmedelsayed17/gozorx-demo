<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierProductController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/supplier/apply', [SupplierController::class, 'apply']);
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::patch('/suppliers/{supplier}/decision', [SupplierController::class, 'decide']);
        });

        Route::middleware('supplier.approved')->group(function () {
            Route::post('/supplier/products', [SupplierProductController::class, 'store']);
        });
    });
});
