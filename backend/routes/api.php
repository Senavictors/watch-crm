<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QualityController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/csrf-cookie', [AuthController::class, 'csrfCookie']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-recovery');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-recovery');

    Route::middleware('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->middleware('permission:dashboard.view');

        Route::get('/shipping/schedule', [ShippingController::class, 'schedule'])->middleware('permission:shipping.view');
        Route::put('/shipping/schedule', [ShippingController::class, 'updateSchedule'])->middleware('permission:shipping.update');
        Route::get('/shipping/queue', [ShippingController::class, 'queue'])->middleware('permission:shipping.view');

        Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
        Route::get('/customers/{id}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
        Route::put('/customers/{id}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
        Route::patch('/customers/{id}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');
        Route::post('/customers/{id}/friction-notes', [CustomerController::class, 'addFrictionNote'])->middleware('permission:customers.update');

        Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::put('/products/{id}', [ProductController::class, 'update'])->middleware('permission:products.update');
        Route::patch('/products/{id}', [ProductController::class, 'update'])->middleware('permission:products.update');
        Route::patch('/products/{id}/add-qty', [ProductController::class, 'addQty'])->middleware('permission:products.update');
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

        Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
        Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
        Route::put('/categories/{id}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
        Route::patch('/categories/{id}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

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

        Route::get('/returns/metadata', [ReturnController::class, 'metadata'])->middleware('permission:returns.view');
        Route::get('/returns', [ReturnController::class, 'index'])->middleware('permission:returns.view');
        Route::post('/returns', [ReturnController::class, 'store'])->middleware('permission:returns.create');
        Route::put('/returns/{id}', [ReturnController::class, 'update'])->middleware('permission:returns.update');
        Route::patch('/returns/{id}', [ReturnController::class, 'update'])->middleware('permission:returns.update');
        Route::delete('/returns/{id}', [ReturnController::class, 'destroy'])->middleware('permission:returns.delete');

        Route::get('/commissions', [CommissionController::class, 'index'])->middleware('permission:commissions.view');
        Route::post('/commissions/pay', [CommissionController::class, 'pay'])->middleware('permission:commissions.pay');

        Route::get('/expenses/metadata', [ExpenseController::class, 'metadata'])->middleware('permission:expenses.view');
        Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.view');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
        Route::put('/expenses/{id}', [ExpenseController::class, 'update'])->middleware('permission:expenses.update');
        Route::patch('/expenses/{id}', [ExpenseController::class, 'update'])->middleware('permission:expenses.update');
        Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');

        Route::get('/goals/metadata', [GoalController::class, 'metadata'])->middleware('permission:goals.view');
        Route::get('/goals', [GoalController::class, 'index'])->middleware('permission:goals.view');
        Route::post('/goals', [GoalController::class, 'store'])->middleware('permission:goals.create');
        Route::put('/goals/{id}', [GoalController::class, 'update'])->middleware('permission:goals.update');
        Route::patch('/goals/{id}', [GoalController::class, 'update'])->middleware('permission:goals.update');
        Route::delete('/goals/{id}', [GoalController::class, 'destroy'])->middleware('permission:goals.delete');

        Route::get('/waitlist/metadata', [WaitlistController::class, 'metadata'])->middleware('permission:waitlist.view');
        Route::get('/waitlist', [WaitlistController::class, 'index'])->middleware('permission:waitlist.view');
        Route::post('/waitlist', [WaitlistController::class, 'store'])->middleware('permission:waitlist.create');
        Route::put('/waitlist/{id}', [WaitlistController::class, 'update'])->middleware('permission:waitlist.update');
        Route::patch('/waitlist/{id}', [WaitlistController::class, 'update'])->middleware('permission:waitlist.update');
        Route::delete('/waitlist/{id}', [WaitlistController::class, 'destroy'])->middleware('permission:waitlist.delete');

        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.manage');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
        Route::patch('/users/{id}', [UserController::class, 'update'])->middleware('permission:users.manage');
        Route::patch('/users/{id}/active', [UserController::class, 'toggleActive'])->middleware('permission:users.manage');
        Route::patch('/users/{id}/password', [UserController::class, 'resetPassword'])->middleware('permission:users.manage');
    });
});
