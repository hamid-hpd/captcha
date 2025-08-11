<?php
if (!function_exists('captcha')) {
    function captcha(string $config = 'default') {
        return app('captcha')->create($config);
    }
}

if (!function_exists('captcha_get_src')) {
    function captcha_get_src(string $config = 'default') {
        return app('captcha')->captchaGetSrc($config);
    }
}

if (!function_exists('captcha_get_img')) {
    function captcha_get_img(string $config = 'default', array $attribs = []) {
        return app('captcha')->captchaGetImg($config, $attribs);
    }
}

if (!function_exists('captcha_check')) {
    function captcha_check(string $value) {
        return app('captcha')->captchaCheck($value);
    }
}
