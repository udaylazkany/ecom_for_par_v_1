<?php

use App\Http\Controllers\CartController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CheckoutController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
 Route::post('/products', [ProductController::class, 'store']);

});
Route::middleware(['auth:sanctum', 'role:user'])->group(function () {
 Route::post('/cart/add', [CartController::class, 'add']);
 Route::post('/checkout', [CheckoutController::class, 'checkout']);


});
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::get('/getallproduct', [ProductController::class, 'getallproduct']);
Route::get('/baseline', [ProductController::class, 'baseline']);
Route::get('/loadBalancedFetchParallel', [ProductController::class, 'loadBalancedFetchParallel']);