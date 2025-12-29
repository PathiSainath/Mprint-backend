<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\OfferBarController;

Route::get('/health', fn() => response()->json([
  'success' => true,
  'message' => 'API is running',
  'timestamp' => now()->toIso8601String()
]));

// Public: categories/products
Route::prefix('categories')->group(function () {
  Route::get('/', [CategoryController::class, 'index']);
  Route::get('/featured', [CategoryController::class, 'featured']);
  Route::get('/{slug}', [CategoryController::class, 'show']);
  Route::post('/', [CategoryController::class, 'store']);
  Route::put('/{id}', [CategoryController::class, 'update']);
  Route::delete('/{id}', [CategoryController::class, 'destroy']);
});

Route::prefix('products')->group(function () {
  Route::get('/', [ProductController::class, 'index']);
  Route::get('/featured', [ProductController::class, 'featured']);
  Route::get('/new-arrivals', [ProductController::class, 'newArrivals']);
  Route::get('/category/{categorySlug}', [ProductController::class, 'byCategory']);
  Route::get('/{slug}', [ProductController::class, 'show']);
  Route::post('/', [ProductController::class, 'store']);
  Route::put('/{id}', [ProductController::class, 'update']);
  Route::delete('/{id}', [ProductController::class, 'destroy']);
  Route::post('/{id}/increment-views', [ProductController::class, 'incrementViews']);
  Route::get('/{slug}/related', [ProductController::class, 'relatedProducts']);
});

// Public: banners & offer bars
Route::prefix('banners')->group(function () {
  Route::get('/', [BannerController::class, 'index']);
  Route::post('/', [BannerController::class, 'store']);
  Route::put('/{id}', [BannerController::class, 'update']);
  Route::delete('/{id}', [BannerController::class, 'destroy']);
});

Route::prefix('offer-bars')->group(function () {
  Route::get('/', [OfferBarController::class, 'index']);
  Route::post('/', [OfferBarController::class, 'store']);
  Route::put('/{id}', [OfferBarController::class, 'update']);
  Route::delete('/{id}', [OfferBarController::class, 'destroy']);
});

// Public: auth
Route::prefix('auth')->group(function () {
  Route::post('/register', [AuthController::class, 'register']);
  Route::post('/login', [AuthController::class, 'login']);
});

// Protected: user + cart + favorites (login required)
Route::middleware('auth:sanctum')->group(function () {
  Route::post('/logout', [AuthController::class, 'logout']);
  Route::get('/profile', [AuthController::class, 'profile']);
  Route::get('/user', fn(\Illuminate\Http\Request $r) => response()->json(['success'=>true,'user'=>$r->user()]));

  Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/add', [CartController::class, 'addToCart']);
    Route::put('/update/{id}', [CartController::class, 'updateQuantity']);
    Route::delete('/remove/{id}', [CartController::class, 'removeFromCart']);
    Route::delete('/clear', [CartController::class, 'clearCart']);
    Route::get('/count', [CartController::class, 'getCartCount']);
    Route::get('/total', [CartController::class, 'getCartTotal']);
  });

  Route::prefix('favorites')->group(function () {
    Route::get('/', [FavoriteController::class, 'index']);
    Route::post('/add', [FavoriteController::class, 'add']);
    Route::post('/toggle', [FavoriteController::class, 'toggle']);
    Route::delete('/remove/{productId}', [FavoriteController::class, 'remove']);
    Route::get('/check/{productId}', [FavoriteController::class, 'check']);
    Route::get('/count', [FavoriteController::class, 'count']);
    Route::delete('/clear', [FavoriteController::class, 'clear']);
  });
});
