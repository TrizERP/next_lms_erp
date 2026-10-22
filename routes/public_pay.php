<?php

use App\Http\Controllers\fees\online_fees\public_fee_pay_api_controller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Parent fee payment without login - /api/public-pay/{code}/...
|--------------------------------------------------------------------------
| Registered in RouteServiceProvider::mapPublicFeePayRoutes(). Deliberately NOT behind api.session / api.jwt: the parent has no
| account. Safety comes from the controller (the school is named by the link, every call must carry the mobile registered for the
| student, the amount is recomputed server-side) and from the limiters below. See docs/PUBLIC_FEE_PAY.md.
*/
Route::prefix('public-pay/{code}')->where(['code' => '[A-Za-z0-9_-]{1,40}'])->group(function () {
    $c = public_fee_pay_api_controller::class;

    Route::get('/', [$c, 'school'])->middleware('throttle:public-pay-school');
    Route::post('lookup', [$c, 'lookup'])->middleware('throttle:public-pay-lookup');
    Route::post('fees', [$c, 'fees'])->middleware('throttle:public-pay-fees');
    Route::post('pay', [$c, 'pay'])->middleware('throttle:public-pay-start');
    Route::post('checkout', [$c, 'checkout'])->middleware('throttle:public-pay-start');
    Route::post('razorpay/confirm', [$c, 'confirmRazorpay'])->middleware('throttle:public-pay-fees');
});
