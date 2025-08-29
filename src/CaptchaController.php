<?php
namespace Hpd\Captcha;

use Illuminate\Routing\Controller;

class CaptchaController extends Controller
{
    public function getCaptcha(Captcha $captcha, string $config = 'default')
    {
        // Validate config parameter
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config)) {
            abort(400, 'Invalid CAPTCHA configuration');
        }
        return $captcha->create($config);
    }

    public function getCaptchaApi(Captcha $captcha, string $config = 'default')
    {
        // Validate config parameter
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config)) {
            abort(400, 'Invalid CAPTCHA configuration');
        }
        return $captcha->create($config, true);
    }
}