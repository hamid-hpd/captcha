<?php

namespace Hpd\Captcha;
use Illuminate\Routing\Controller;

class CaptchaController extends Controller
{
public function getCaptcha( Captcha $captcha,string $config = 'default'){
    ob_start();
    ob_clean();
  return $captcha->create($config);
}
    public function getCaptchaApi( Captcha $captcha,string $config = 'default'){
        return $captcha->create($config,true);
    }
}
