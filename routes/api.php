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

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['middleware' => ['cors']], function () {
    Route::group(['middleware' => ['auth:api']], function () {


        Route::post('/users/create', 'Api\UserController@create');
        Route::get('/users', 'Api\UserController@getAll');
        Route::get('/users/{id}', 'Api\UserController@get');
        Route::put('/users/{id}', 'Api\UserController@create');
        Route::delete('/users/{id}', 'Api\UserController@delete');


        Route::post('/inwards/create', 'Api\InwardController@create');
        Route::get('/inwards/{filter_data}', 'Api\InwardController@getAll');
        Route::get('/inwards/edit/{id}', 'Api\InwardController@get');
        Route::put('/inwards/{id}', 'Api\InwardController@create');
        Route::delete('/inwards/{id}', 'Api\InwardController@delete');

        Route::post('/inters/create', 'Api\InterController@create');
        Route::get('/inters/{filter_data}', 'Api\InterController@getAll');
        Route::get('/inters/edit/{id}', 'Api\InterController@get');
        Route::put('/inters/{id}', 'Api\InterController@create');
        Route::delete('/inters/{id}', 'Api\InterController@delete');

        Route::post('/balances/create', 'Api\BalanceController@create');
        Route::get('/balances/{filter_data}', 'Api\BalanceController@getAll');
        Route::get('/balances/edit/{id}', 'Api\BalanceController@get');
        Route::put('/balances/{id}', 'Api\BalanceController@create');
        Route::delete('/balances/{id}', 'Api\BalanceController@delete');

        Route::post('/outwards/create', 'Api\OutwardController@create');
        Route::get('/outwards/{filter_data}', 'Api\OutwardController@getAll');
        Route::get('/outwards/edit/{id}', 'Api\OutwardController@get');
        Route::put('/outwards/{id}', 'Api\OutwardController@create');
        Route::delete('/outwards/{id}', 'Api\OutwardController@delete');

        Route::get('/dashboard/{filter_data}', 'Api\HomeController@getAll');
        Route::get('/getSelectDatas/{id}', 'Api\HomeController@getSelectDatas');

        Route::post('/masters/create/{master}', 'Api\MasterController@create');
        Route::get('/masters/{master}', 'Api\MasterController@getAll');
        Route::get('/masters/{master}/{id}', 'Api\MasterController@get');
        Route::put('/masters/{master}/{id}', 'Api\MasterController@create');
        Route::delete('/masters/{master}/{id}', 'Api\MasterController@delete');
        Route::post('/auth/logout', 'Api\LoginController@logout');
        Route::get('/getLogfiles', 'Api\HomeController@getLogfiles');
    });
    Route::post('/auth/check', 'Api\LoginController@checkAuth');
    Route::post('/auth/checking', 'Api\LoginController@checking_authenticate');
});
Route::get('/testgetLogfiles', 'Api\HomeController@getLogfiles');
