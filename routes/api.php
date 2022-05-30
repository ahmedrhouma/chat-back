<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\ApiAuthController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\UsersController;

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

Route::middleware('auth:api')->get('/token/revoke', function (Request $request) {
    DB::table('oauth_access_tokens')
        ->where('user_id', $request->user()->id)
        ->update([
            'revoked' => true
        ]);
    return response()->json('DONE');
});
Route::group(['middleware' => ['cors']], function () {
    Route::post('/login', [ApiAuthController::class,'login'])->name('login.api');
    Route::post('/register',[ApiAuthController::class,'register'])->name('register.api');
});
Route::middleware('auth:api')->group(function () {
    Route::post('/user-detail', [ApiAuthController::class, 'userDetail'])->name('user.details');
    Route::post('/users', [UsersController::class, 'index'])->name('users.list');
    Route::post('/logout', [ApiAuthController::class,'logout'])->name('logout.api');
    Route::post('/messages', [MessagesController::class,'index'])->name('messages.api');
    Route::post('/conversations', [MessagesController::class,'conversations'])->name('messages.api');
});
 