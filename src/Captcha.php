<?php

namespace Hpd\Captcha;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Illuminate\Session\Store as Session;
use Illuminate\Support\Facades\Cache;

class Captcha
{
    protected $config;
    protected string $bgColor = "#000000";
    protected string $color = "#FFFFFF";
    protected string $flakeColor = "#FFFFFF";
    protected bool $blur = false;
    protected int $alpha = 0;
    protected bool $flake = false;
    protected bool $line = false;
    protected bool $difficult = false;
    protected int $length = 5;
    protected int $width = 110;
    protected int $height = 40;
    protected int $expire = 60;
    protected bool $sensitive = false;
    protected array $characters;
    protected bool $lowercase = true;
    protected bool $uppercase = false;
    protected bool $digits = false;
    protected string $fontDir = '';
    protected string $font = 'libre.ttf';
    protected int $fontSize = 24;
    protected $str;
    protected $session;
    protected $image;

    public function __construct(Repository $config, Str $str, Session $session)
    {
        $this->config = $config->get('config');
        $this->characters = $config->get('config.characters');
        $this->fontDir = dirname(__DIR__) . '/assets/fonts/';
        $this->str = $str;
        $this->session = $session;
    }

    protected function initial(string $config)
    {
        foreach ($this->config[$config] as $Key => $value) {
            $this->$Key = $value;
        }
    }

    public function create(string $config = 'default', $is_api = false)
    {
        $this->initial($config);
        $text = $this->randomString();
        if (!$this->sensitive) {
            $codeString = $this->str->lower($text);
        } else {
            $codeString = $text;
        }

        // ** اصلاح: تولید توکن امن به جای Hash::make **
        $token = bin2hex(random_bytes(32)); // توکن 64 کاراکتری امن
        $hashedCode = hash_hmac('sha256', $codeString, config('app.key'));

        // داده‌های کپچا را به صورت آرایه ذخیره میکنیم
        $captchaData = [
            'sensitive' => $this->sensitive,
            'code' => $codeString,
            'token' => $token,
            'hash' => $hashedCode,
            'expire' => now()->addSeconds($this->expire)
        ];

        if ($is_api) {
            Cache::put('cap_' . $token, $captchaData, $this->expire);
        } else {
            $this->session->put('captcha', $captchaData);
        }

        $this->image = imagecreatetruecolor($this->width, $this->height);

        if ($this->bgColor == 'random') {
            $bgColor = imagecolorallocate($this->image, random_int(100, 255), random_int(100, 255), random_int(100, 255));
        } else {
            list($br, $bg, $bb) = sscanf($this->bgColor, "#%02x%02x%02x");
            $bgColor = imagecolorallocate($this->image, $br, $bg, $bb);
        }
        imagefill($this->image, 0, 0, $bgColor);

        $font = $this->fontDir . $this->font;
        $characters = str_split($text);

        if ($this->color == 'random') {
            for ($i = 0, $x = 5; $i < $this->length; $i++, $x += 20) {
                $angel = [random_int(3, 35), random_int(350, 360)];
                $y = random_int(30, 35);
                $textColor = imagecolorallocatealpha($this->image, random_int(0, 255), random_int(0, 255), random_int(0, 255), $this->alpha);
                imagettftext($this->image, $this->fontSize, $angel[random_int(0, 1)], $x, $y, $textColor, $font, $characters[$i]);
            }
        } else {
            list($cr, $cg, $cb) = sscanf($this->color, "#%02x%02x%02x");
            $textColor = imagecolorallocatealpha($this->image, $cr, $cg, $cb, $this->alpha);
            for ($i = 0, $x = 5; $i < $this->length; $i++, $x += 20) {
                $angel = [random_int(3, 35), random_int(350, 360)];
                $y = random_int(30, 35);
                imagettftext($this->image, $this->fontSize, $angel[random_int(0, 1)], $x, $y, $textColor, $font, $characters[$i]);
            }
        }

        $xRange = $this->difficult ? [0, 3] : [1, 4];
        $yRange = $this->difficult ? [0, 3] : [1, 4];

        if ($this->blur) {
            imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($this->image, IMG_FILTER_SELECTIVE_BLUR);
        }

        if ($this->flake) {
            $this->addFlake($xRange, $yRange);
        }
        if ($this->line) {
            $this->addLines();
        }

        // تصویر را قبل از تخریب ذخیره می‌کنیم
        ob_start();
        imagepng($this->image);
        $imgData = ob_get_clean();

        imagedestroy($this->image);

        $imgBase64 = base64_encode($imgData);

        if ($is_api) {
            return [
                'token' => $token,
                'sensitive' => $this->sensitive,
                'image' => 'data:image/png;base64,' . $imgBase64
            ];
        }

        return response($imgData, 200, ["Content-Type" => "image/png"]);
    }

    protected function randomString(): string
    {
        $string = '';
        if ($this->lowercase)
            $string .= $this->characters['lowercase'];
        if ($this->uppercase)
            $string .= $this->characters['uppercase'];
        if ($this->digits)
            $string .= $this->characters['digits'];

        $text = "";
        $size = strlen($string);

        for ($i = 0; $i < $this->length; $i++) {
            $text .= $string[random_int(0, $size - 1)];
        }
        return $text;
    }

    protected function addFlake($xRange, $yRange)
    {
        if ($this->flakeColor == 'random') {
            for ($x = 0; $x < $this->width; $x += random_int($xRange[0], $xRange[1])) {
                for ($y = 0; $y < $this->height; $y += random_int($yRange[0], $yRange[1])) {
                    $flakeColor = imagecolorallocate($this->image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                    imagesetpixel($this->image, $x, $y, $flakeColor);
                }
            }
        } else {
            list($r, $g, $b) = sscanf($this->flakeColor, "#%02x%02x%02x");
            $flakeColor = imagecolorallocate($this->image, $r, $g, $b);
            for ($x = 0; $x < $this->width; $x += random_int($xRange[0], $xRange[1])) {
                for ($y = 0; $y < $this->height; $y += random_int($yRange[0], $yRange[1])) {
                    imagesetpixel($this->image, $x, $y, $flakeColor);
                }
            }
        }
    }

    protected function addLines()
    {
        $lines = random_int(3, 4);
        for ($i = 0; $i < $lines; $i++) {
            $x1 = random_int(5, $this->width - 25);
            $y1 = random_int(5, $this->height - 5);
            $x2 = $x1 + 25;
            $y2 = random_int($y1, $y1 + 15);
            $color = imagecolorallocate($this->image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagesetthickness($this->image, random_int(2, 3));
            imageline($this->image, $x1, $y1, $x2, $y2, $color);
        }
    }

    public function captchaGetSrc(string $config = 'default'): string
    {
        return url('captcha/' . $config) . '?' . $this->str->random(8);
    }

    public function captchaGetImg(string $config = 'default', array $attribs = []): string
    {
        $attributes = '';
        foreach ($attribs as $attrib => $value) {
            if ($attrib === 'src') {
                continue;
            }
            // Escape attributes برای جلوگیری از XSS
            $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $attributes .= $attrib . "='" . $safeValue . "' ";
        }
        return "<img src='" . e($this->captchaGetSrc($config)) . "' " . $attributes . ">";
    }

    public function captchaCheck($input, ?string $token = null): bool
    {
        if ($token) {
            // چک کردن با استفاده از Cache و توکن
            $data = Cache::get('cap_' . $token);
            if (!$data) return false;
            $code = $this->sensitive ? $data['code'] : strtolower($data['code']);
            $hash = $data['hash'];
            $inputToCheck = $this->sensitive ? $input : strtolower($input);

            // چک کردن با هش امن
            if (hash_equals($hash, hash_hmac('sha256', $inputToCheck, config('app.key')))) {
                Cache::forget('cap_' . $token);
                return true;
            }
            return false;
        } else {
            $data = $this->session->get('captcha');
            if (!$data) return false;
            $code = $this->sensitive ? $data['code'] : strtolower($data['code']);
            $inputToCheck = $this->sensitive ? $input : strtolower($input);
            $hash = $data['hash'];

            if (hash_equals($hash, hash_hmac('sha256', $inputToCheck, config('app.key')))) {
                $this->session->forget('captcha');
                return true;
            }
            return false;
        }
    }
}
