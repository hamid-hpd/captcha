<?php
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('captcha/{config?}', '\Hpd\Captcha\CaptchaController@getCaptcha')
        ->where('config', '[a-zA-Z0-9_-]+');
});

Route::middleware(['api'])->group(function () {
    Route::get('captcha/api/{config?}', '\Hpd\Captcha\CaptchaController@getCaptchaApi')
        ->where('config', '[a-zA-Z0-9_-]+');
});
