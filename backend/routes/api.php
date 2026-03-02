<?php

use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ModelController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QualityController;
use Illuminate\Support\Facades\Route;

Route::get('/customers', [CustomerController::class, 'index']);
Route::post('/customers', [CustomerController::class, 'store']);
Route::put('/customers/{id}', [CustomerController::class, 'update']);
Route::patch('/customers/{id}', [CustomerController::class, 'update']);
Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::patch('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);
Route::get('/brands', [BrandController::class, 'index']);
Route::post('/brands', [BrandController::class, 'store']);
Route::put('/brands/{id}', [BrandController::class, 'update']);
Route::patch('/brands/{id}', [BrandController::class, 'update']);
Route::delete('/brands/{id}', [BrandController::class, 'destroy']);
Route::get('/qualities', [QualityController::class, 'index']);
Route::post('/qualities', [QualityController::class, 'store']);
Route::put('/qualities/{id}', [QualityController::class, 'update']);
Route::patch('/qualities/{id}', [QualityController::class, 'update']);
Route::delete('/qualities/{id}', [QualityController::class, 'destroy']);
Route::get('/models', [ModelController::class, 'index']);
Route::post('/models', [ModelController::class, 'store']);
Route::put('/models/{id}', [ModelController::class, 'update']);
Route::patch('/models/{id}', [ModelController::class, 'update']);
Route::delete('/models/{id}', [ModelController::class, 'destroy']);
Route::get('/orders', [OrderController::class, 'index']);
