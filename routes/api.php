<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/oauth2/{provider}', [AccountController::class, 'auth']);
Route::get('/oauth2/{provider}/callback', [AccountController::class, 'callback']);
Route::get('/event', [AccountController::class, 'getEvent']);
Route::post('/pushEvent', [AccountController::class, 'pushEvent']);
