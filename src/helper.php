<?php
if (!function_exists('captcha')) {
    function captcha(string $config = 'default')
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config)) {
            throw new \InvalidArgumentException('Invalid CAPTCHA configuration');
        }
        return app('captcha')->create($config);
    }
}

if (!function_exists('captcha_get_src')) {
    function captcha_get_src(string $config = 'default'): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config)) {
            throw new \InvalidArgumentException('Invalid CAPTCHA configuration');
        }
        return app('captcha')->captchaGetSrc($config);
    }
}

if (!function_exists('captcha_get_html')) {
    function captcha_get_html(string $config = 'default', array $attribs = []): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config)) {
            throw new \InvalidArgumentException('Invalid CAPTCHA configuration');
        }
        return app('captcha')->captchaGetImg($config, $attribs);
    }
}

if (!function_exists('captcha_check')) {
    function captcha_check(string $value): bool
    {
        if ($value === null || $value === false || $value === "" || strlen($value) > 100) {
            return false;
        }
        return app('captcha')->captchaCheck($value);
    }
}

if (!function_exists('captcha_check_api')) {
    function captcha_check_api(string $value, string $token): bool
    {
        if ($value === null || $value === false || $value === "" || empty($token) || strlen($value) > 100 || strlen($token) !== 64) {
            return false;
        }
        return app('captcha')->captchaCheck($value, $token);
    }
}