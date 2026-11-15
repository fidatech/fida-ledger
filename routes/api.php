<?php

use App\Http\Controllers\Api\V1Controller;
use App\Http\Middleware\ApiAuth;
use Illuminate\Support\Facades\Route;

/*
| The API for other programs (mobile and desktop apps). Addresses start with /api/v1.
| Sign in with "Authorization: Bearer <key>"; keys are made under Settings > API Access.
| A key works in one company as one user, so the checks below are the same as in the browser.
*/
Route::prefix('v1')->middleware(['throttle:120,1', ApiAuth::class])->group(function () {
    Route::get('/me', [V1Controller::class, 'me']);
    Route::get('/summary', [V1Controller::class, 'summary'])->middleware('can.do:money.view');

    Route::get('/accounts', [V1Controller::class, 'accounts'])->middleware('can.do:accounts.view');
    Route::get('/accounts/{id}', [V1Controller::class, 'account'])->whereNumber('id')->middleware('can.do:accounts.view');

    Route::get('/money', [V1Controller::class, 'money'])->middleware('can.do:money.view');
    Route::post('/money', [V1Controller::class, 'storeMoney'])->middleware('can.do:money.create');

    Route::get('/invoices', [V1Controller::class, 'invoices'])->middleware('can.do:invoices.view');
    Route::get('/invoices/{id}', [V1Controller::class, 'invoice'])->whereNumber('id')->middleware('can.do:invoices.view');

    Route::get('/products', [V1Controller::class, 'products'])->middleware(['module:products', 'can.do:products.view']);
});
