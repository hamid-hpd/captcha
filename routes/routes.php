<?php
use Illuminate\Support\Facades\Route;

Route::prefix('hpd')->group(function () {

    //  Web Route
    Route::get('captcha/{config?}', '\Hpd\Captcha\CaptchaController@getCaptcha')
        ->middleware('web')
        ->where('config', '[a-zA-Z0-9_-]+');

    // ApI Route
    Route::get('captcha/api/{config?}', '\Hpd\Captcha\CaptchaController@getCaptchaApi')
        ->middleware('api')
        ->where('config', '[a-zA-Z0-9_-]+');

});