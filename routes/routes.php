<?php

use Illuminate\Support\Facades\Route;
use Hpd\Captcha\Captcha;

Route::get('/captcha/{config?}', function ($config = 'default', Captcha $captcha) {
    return $captcha->create($config);
});

Route::post('/captcha/verify', function (\Illuminate\Http\Request $request, Captcha $captcha) {
    $input = $request->input('captcha');
    $token = $request->input('token');

    if ($token) {
        $valid = $captcha->captchaCheckApi($input, $token);
    } else {
        $valid = $captcha->captchaCheck($input);
    }

    return response()->json(['success' => $valid]);
});
