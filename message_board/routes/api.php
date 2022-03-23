<?php

use Illuminate\Http\Request;
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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('message', 'MessageController@getAll');
Route::get('message/{id}', 'MessageController@get');

Route::middleware(['api.auth'])->group(function () {
    Route::post('message', 'MessageController@create');
    Route::put('message/{id}', 'MessageController@update');
    Route::patch('message/{id}', 'MessageController@like');
    Route::delete('message/{id}', 'MessageController@delete');
});

Route::post('login', 'LoginController@login');
Route::post('logout', 'LoginController@logout');
