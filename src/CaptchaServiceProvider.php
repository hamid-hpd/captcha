<?php

namespace Hpd\Captcha;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class CaptchaServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Load helper file
        $file = realpath(__DIR__.'/helper.php');
        if ($file && file_exists($file)) {
            require_once($file);
        }

        /**
         * Merge default config
         * (This only loads package defaults — does NOT override published config.)
         */
        $this->mergeConfigFrom(
             realpath(__DIR__.'/../config/config.php'),
            'hpd_captcha'
        );
                $this->app->singleton('captcha', function($app) {
            return new Captcha(
                $app['Illuminate\Contracts\Config\Repository'],
                $app['Illuminate\Support\Str'],
                $app['Illuminate\Session\Store']
            );
        });
    }

    public function boot()
    {
        /**
         * Publish main config
         */
        $this->publishes([
            realPath(__DIR__ . '/../config/config.php') => config_path('hpd_captcha.php'),
        ], 'hpd-captcha-config');


        /**
         * Publish words file (5000+ words)
         * Location: storage/app/hpd/captcha/
         */
        $this->publishes([
            realPath(__DIR__ . '/../resources/data/words_en.php') => storage_path('app/hpd/captcha/words_en.php'),
        ], 'hpd-captcha-words');


        /**
         * Load routes
         */
        $this->loadRoutesFrom(realpath(__DIR__ . '/../routes/routes.php'));


       Validator::extend('captcha', function($attribute, $value, $parameters, $validator) {
            if ($value === null || $value === false || $value === "" || strlen($value) > 100) {
                return false;
            }
            $result = captcha_check($value);
            return $result;
        }, 'The :attribute field is not a valid CAPTCHA code.');

        Validator::extend('captcha_api', function($attribute, $value, $parameters, $validator) {
            if ($value === null || $value === false || $value === ""|| empty($parameters[0]) || strlen($value) > 100 || strlen($parameters[0]) !== 64) {
                return false;
            }
            $result = captcha_check_api($value, $parameters[0]);
            return $result;
        }, 'The :attribute field is not a valid CAPTCHA code.');
    }
}