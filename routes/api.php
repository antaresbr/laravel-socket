<?php

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

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

Route::get('/alive', function (Request $request) {
    return response()->json([
        'package' => ai_socket_infos(),
        'env' => app()->environmentFile(),
        'serverDateTime' => Carbon::now()->toString(),
    ]);
});

$auth = config('socket.auth.api') ?: config('auth.defaults.guard');

if (!empty($auth)) {
    Route::middleware('auth:' . $auth)->group(function () {
        Route::get('/get/{ids}', 'SocketController@get');
    });
} else {
    Route::get('/get/{ids}', 'SocketController@get');
}
