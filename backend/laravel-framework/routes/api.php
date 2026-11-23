<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SupplierDocumentController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierProductController;
use App\Http\Controllers\Api\SupplierDashboardController;
use App\Http\Controllers\Api\SupplierOrderController;
use App\Http\Controllers\Api\PublicProductController;
use App\Http\Controllers\Api\PublicCategoryController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\BuyerRfqController;

Route::prefix('v1')->group(function () {
    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/categories', [PublicCategoryController::class, 'index']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/supplier/register', [SupplierController::class, 'registerAndApply'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
          Route::post('/orders', [OrderController::class, 'store']);
          Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
          Route::post('/orders/{order}/deposit-receipt', [PaymentController::class, 'uploadDepositReceipt']);

        Route::middleware('role:supplier')->group(function () {
            Route::post('/supplier/apply', [SupplierController::class, 'apply']);
            Route::get('/supplier/application', [SupplierController::class, 'application']);
            Route::post('/supplier/documents/{documentType}/reupload', [SupplierDocumentController::class, 'reupload']);

            Route::middleware('supplier.approved')->group(function () {
                Route::get('/supplier/dashboard', [SupplierDashboardController::class, 'index']);
                Route::get('/supplier/orders', [SupplierOrderController::class, 'index']);
                Route::get('/supplier/orders/{type}/{id}', [SupplierOrderController::class, 'show']);
                Route::get('/supplier/products', [SupplierProductController::class, 'index']);
                Route::get('/supplier/products/{product}', [SupplierProductController::class, 'show']);
                Route::post('/supplier/products', [SupplierProductController::class, 'store']);
                Route::put('/supplier/products/{product}', [SupplierProductController::class, 'update']);
                Route::patch('/supplier/products/{product}', [SupplierProductController::class, 'update']);
            });
        });

        Route::middleware('role:buyer')->group(function () {
            Route::post('/rfq', [BuyerRfqController::class, 'store']);
        });

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
            Route::get('/orders', [AdminOrderController::class, 'index']);
            Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
            Route::patch('/orders/{order}/shipping-cost', [AdminOrderController::class, 'setShippingCost']);
            Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
            Route::post('/orders/{order}/collect-balance', [AdminOrderController::class, 'collectBalance']);
            Route::get('/payments/deposit-receipts', [PaymentController::class, 'pendingDepositReceipts']);
            Route::get('/payments/deposit-receipts/{paymentTransaction}/receipt', [PaymentController::class, 'showDepositReceipt']);
            Route::patch('/payments/deposit-receipts/{paymentTransaction}/decision', [PaymentController::class, 'decideDeposit']);
            Route::get('/products', [AdminProductController::class, 'index']);
            Route::patch('/products/{product}/decision', [AdminProductController::class, 'decide']);
            Route::patch('/suppliers/{supplier}/decision', [SupplierController::class, 'decide']);
            Route::get('/supplier-documents/{document}/file', [SupplierDocumentController::class, 'showFile']);
            Route::patch('/supplier-documents/{document}/decision', [SupplierDocumentController::class, 'decide']);
        });


    });
});
