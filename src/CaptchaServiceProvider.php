<?php
namespace Hpd\Captcha;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class CaptchaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        /* Include helpers */
        $file = __DIR__.'/helper.php';
        if (file_exists($file)) {
            require_once($file);
        }
        /* Merge configurations */
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'config');

        /* Bind captch */
        $this->app->bind('captcha',function($app){
            return new Captcha(
                $app['Illuminate\Contracts\Config\Repository'],
                $app['Illuminate\Support\Str'],
                $app['Illuminate\Session\Store']
            );
        });

    }
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        /* Publish configuration file */
        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('config.php'),
        ]);

        /* Routing */
        $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');

        /* Extend validator class*/
       Validator::extend('captcha',function($attribute,$value,$parameters,$validator)
       {
           return captcha_check($value);
       });
       Validator::extend('captcha_api',function ($attribute,$value,$parameters,$validator){
           return captcha_check_api($value,$parameters[0],$parameters[1] ?? 'default');
       });
    }
}
