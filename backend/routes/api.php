<?php

use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QualityController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/csrf-cookie', [AuthController::class, 'csrfCookie']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-recovery');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-recovery');

    Route::middleware('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
        Route::put('/customers/{id}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
        Route::patch('/customers/{id}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

        Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::put('/products/{id}', [ProductController::class, 'update'])->middleware('permission:products.update');
        Route::patch('/products/{id}', [ProductController::class, 'update'])->middleware('permission:products.update');
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

        Route::get('/brands', [BrandController::class, 'index'])->middleware('permission:brands.view');
        Route::post('/brands', [BrandController::class, 'store'])->middleware('permission:brands.create');
        Route::put('/brands/{id}', [BrandController::class, 'update'])->middleware('permission:brands.update');
        Route::patch('/brands/{id}', [BrandController::class, 'update'])->middleware('permission:brands.update');
        Route::delete('/brands/{id}', [BrandController::class, 'destroy'])->middleware('permission:brands.delete');

        Route::get('/qualities', [QualityController::class, 'index'])->middleware('permission:qualities.view');
        Route::post('/qualities', [QualityController::class, 'store'])->middleware('permission:qualities.create');
        Route::put('/qualities/{id}', [QualityController::class, 'update'])->middleware('permission:qualities.update');
        Route::patch('/qualities/{id}', [QualityController::class, 'update'])->middleware('permission:qualities.update');
        Route::delete('/qualities/{id}', [QualityController::class, 'destroy'])->middleware('permission:qualities.delete');

        Route::get('/models', [ModelController::class, 'index'])->middleware('permission:models.view');
        Route::post('/models', [ModelController::class, 'store'])->middleware('permission:models.create');
        Route::put('/models/{id}', [ModelController::class, 'update'])->middleware('permission:models.update');
        Route::patch('/models/{id}', [ModelController::class, 'update'])->middleware('permission:models.update');
        Route::delete('/models/{id}', [ModelController::class, 'destroy'])->middleware('permission:models.delete');

        Route::get('/orders/metadata', [OrderController::class, 'metadata'])->middleware('permission:orders.view');
        Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view');
        Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:orders.create');
        Route::put('/orders/{id}', [OrderController::class, 'update'])->middleware('permission:orders.update');
        Route::patch('/orders/{id}', [OrderController::class, 'update'])->middleware('permission:orders.update');
        Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->middleware('permission:orders.delete');
    });
});
