<?php
if(!function_exists('captcha')){
    function captcha(string $config='default'){
         return app('captcha')->create($config);
    }
}
if(!function_exists('captcha_get_src()')){
    function captcha_get_src(string $config='default'):string{
        return app('captcha')->captchaGetSrc($config);
    }
}
if(!function_exists('captcha_get_html')){
    function captcha_get_html(string $config='default',array $attribs=[]):string{
        return app('captcha')->captchaGetImg($config,$attribs);
    }
}
if(!function_exists('captcha_check')) {
    function captcha_check(string $value): bool
    {
        return app('captcha')->captchaCheck($value);
    }
}
if(!function_exists('captcha_check_api')){

        function captcha_check_api(string $value,string $key, string $config):bool{
            return app('captcha')->captchaCheckApi($value,$key,$config);
        }
    }


