<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Http\Controllers\BrandController;
use Aliziodev\ProductCatalog\Http\Controllers\CategoryController;
use Aliziodev\ProductCatalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{slug}', [ProductController::class, 'show']);
Route::get('categories', [CategoryController::class, 'index']);
Route::get('brands', [BrandController::class, 'index']);
