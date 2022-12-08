<?php
use Illuminate\Support\Facades\Route;
Route::get('captcha/api/{config?}','\Hpd\Captcha\CaptchaController@getCaptchaApi')->middleware('web');
Route::get('captcha/{config?}', '\Hpd\Captcha\CaptchaController@getCaptcha')->middleware('web');
