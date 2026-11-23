<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierProductController;
use App\Http\Controllers\Api\PublicProductController;
use App\Http\Controllers\Api\PublicCategoryController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminOrderController;

Route::prefix('v1')->group(function () {
    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/categories', [PublicCategoryController::class, 'index']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
          Route::post('/orders', [OrderController::class, 'store']);
          Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
          Route::post('/orders/{order}/deposit-receipt', [PaymentController::class, 'uploadDepositReceipt']);

        Route::post('/supplier/apply', [SupplierController::class, 'apply']);
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::get('/orders', [AdminOrderController::class, 'index']);
            Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
            Route::get('/payments/deposit-receipts', [PaymentController::class, 'pendingDepositReceipts']);
            Route::get('/payments/deposit-receipts/{paymentTransaction}/receipt', [PaymentController::class, 'showDepositReceipt']);
            Route::patch('/payments/deposit-receipts/{paymentTransaction}/decision', [PaymentController::class, 'decideDeposit']);
            Route::get('/products', [AdminProductController::class, 'index']);
            Route::patch('/products/{product}/decision', [AdminProductController::class, 'decide']);
            Route::patch('/suppliers/{supplier}/decision', [SupplierController::class, 'decide']);
        });

        Route::middleware('supplier.approved')->group(function () {
            Route::post('/supplier/products', [SupplierProductController::class, 'store']);
        });
    });
});
