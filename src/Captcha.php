<?php

namespace Hpd\Captcha;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Illuminate\Session\Store as Session;
use Illuminate\Support\Facades\Cache;

class Captcha
{
    protected $config;
    protected string $bgColor = "#000000";//'random'
    protected string $color = "#FFFFFF";  //'random','multi'
    protected string $noiseColor = "#FFFFFF";//'random' 
    protected bool $blur = false;
    protected int $alpha = 0; // 0 -> 1
    protected bool $noise = false;
    protected string $noiseMode = 'cross';//'vertical','horizontal','cross','random'
    protected float $noiseDensity = 0.1; // 0 -> 1.0
    protected float $noiseIntensity = 1.0; // 0 -> 1.0
    protected bool $noiseLines = false;
    protected bool $difficulty = false;
    protected int $length = 5;
    protected int $expire = 60;
    protected bool $sensitive = false;
    protected array $characters;
    protected bool $lowercase = true;
    protected bool $uppercase = false;
    protected bool $digits = false;
    protected string $fontDir = '';
    protected string $font = 'libre.ttf';
    protected int $fontSize = 24;
    protected string $type = 'string';
    protected $str;
    protected $session;
    protected $image;

    public function __construct(Repository $config, Str $str, Session $session)
    {
        $this->config = $config->get('captcha');
        $this->characters = $config->get('captcha.characters');
        $this->fontDir = realpath(dirname(__DIR__) . '/assets/fonts/') . '/';
        $this->str = $str;
        $this->session = $session;
    }

    protected function initial(string $config)
    {
        if (!isset($this->config[$config])) {
            throw new \InvalidArgumentException("Invalid CAPTCHA configuration: {$config}");
        }
        foreach ($this->config[$config] as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    protected function generateMathExpression(): array
    {
        $operations = $this->config['math']['operations'] ?? ['+', '-', '*'];
        $minNumber = $this->config['math']['min_number'] ?? 0;
        $maxNumber = $this->config['math']['max_number'] ?? 20;
        $operation = $operations[array_rand($operations)];
        if ($operation === '*') {
            $num1 = random_int($minNumber, min($maxNumber, 10));
            $num2 = random_int($minNumber, min($maxNumber, 5));
        } else {
            
            $num1 = random_int($minNumber, $maxNumber);
            $num2 = random_int($minNumber, $maxNumber);
        }

        // Avoid negative subtraction result
        if ($operation === '-' && $num1 < $num2) {
            [$num1, $num2] = [$num2, $num1];
        }

        switch ($operation) {
            case '+':
                $result = $num1 + $num2;
                break;
            case '-':
                $result = $num1 - $num2;
                break;
            case '*':
                $result = $num1 * $num2;
                break;
            default:
                throw new \RuntimeException('Invalid math operation');
        }
        $operation = ($operation === '*') ? '×' : $operation;

        $expression = "$num1 $operation $num2 = ?";
        return [
            'expression' => $expression,
            'result' => $result
        ];
    }

    public function create(string $config = 'default', $is_api = false)
    {
        $this->initial($config);
        $isMathCaptcha = $this->type === 'math';

        if ($isMathCaptcha) {
            $mathData = $this->generateMathExpression();
            $text = $mathData['expression'];
            $codeString = (string)$mathData['result'];
        } else {
            $text = $this->randomString();
            $codeString = $this->sensitive ? $text : $this->str->lower($text);
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $hashedCode = hash_hmac('sha256', $codeString, config('app.key'));

        // Store CAPTCHA data
        $captchaData = [
            'sensitive' => $this->sensitive,
            'hash' => $hashedCode,
            'expire' => time() + $this->expire,
            'is_math' => $isMathCaptcha
        ];

        if ($is_api) {
            Cache::put('cap_' . $token, $captchaData, $this->expire);
        } else {
            $this->session->put('captcha', $captchaData);
        }

        $font = $this->fontDir . $this->font;
        if (!file_exists($font)) {
            throw new \RuntimeException('Font file not found: ' . $font);
        }

        // Calculate image dimensions based on text
        $padding = 10; // Margin around text
        $angle = $isMathCaptcha ? 0 : (random_int(0, 1) ? random_int(3, 35) : random_int(350, 360));
        $bbox = imagettfbbox($this->fontSize, $angle, $font, $text);
        $textWidth = abs($bbox[2] - $bbox[0]); // Width of text
        $textHeight = abs($bbox[7] - $bbox[1]); // Height of text

        // Set image dimensions with padding
        $imageWidth = $textWidth + $padding * 2;
        $imageHeight = $textHeight + $padding * 2;

        // Create image
        $this->image = imagecreatetruecolor($imageWidth, $imageHeight);
        if (!$this->image) {
            throw new \RuntimeException('Failed to create CAPTCHA image');
        }

        // Set background color
        if ($this->bgColor == 'random') {
            $bgColor = imagecolorallocate($this->image, random_int(100, 255), random_int(100, 255), random_int(100, 255));
        } else {
            list($br, $bg, $bb) = sscanf($this->bgColor, "#%02x%02x%02x");
            $bgColor = imagecolorallocate($this->image, $br, $bg, $bb);
        }
        imagefill($this->image, 0, 0, $bgColor);

        // Set text color for math CAPTCHA or when color is not 'multi'
        $textColor = null;
        if ($isMathCaptcha || $this->color !== 'multi') {
            if ($this->color == 'random') {
                $textColor = imagecolorallocatealpha($this->image, random_int(0, 255), random_int(0, 255), random_int(0, 255), $this->alpha);
            } else {
                list($cr, $cg, $cb) = sscanf($this->color, "#%02x%02x%02x");
                $textColor = imagecolorallocatealpha($this->image, $cr, $cg, $cb, $this->alpha);
            }
        }

        // Center text in image
        $x = ($imageWidth - $textWidth) / 2; // Center horizontally
        $y = ($imageHeight + $textHeight) / 2; // Center vertically (adjusted for baseline)

        if ($isMathCaptcha) {
            // Render math expression as a single string
            imagettftext($this->image, $this->fontSize, 0, $x, $y, $textColor, $font, $text);
        } else {
            // Render each character separately for string CAPTCHA
            $characters = str_split($text);
            $charWidth = $textWidth / $this->length; // Approximate width per character
            $startX = ($imageWidth - $textWidth) / 2; // Starting x for first character
            for ($i = 0; $i < $this->length; $i++) {
                $angle = random_int(0, 1) ? random_int(3, 35) : random_int(350, 360); // Random angle for each character
                // Use different color for each character if color is 'multi'
                if ($this->color === 'multi') {
                    $textColor = imagecolorallocatealpha($this->image, random_int(0, 255), random_int(0, 255), random_int(0, 255), $this->alpha);
                }
                imagettftext($this->image, $this->fontSize, $angle, $startX + $i * $charWidth, $y, $textColor, $font, $characters[$i]);
            }
        }

        if ($this->blur) {
            imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($this->image, IMG_FILTER_SELECTIVE_BLUR);    
        }

        if ($this->noise) {
            $this->addNoise($imageWidth, $imageHeight);
        }

        if ($this->noiseLines) {
            $this->addNoiseLines($imageWidth, $imageHeight);
        }

        // Capture image data
        ob_start();
        imagepng($this->image);

        $imgData = ob_get_clean();

        imagedestroy($this->image);

        $imgBase64 = base64_encode($imgData);

        if ($is_api) {
            return [
                'token' => $token,
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

        if (empty($string)) {
            throw new \RuntimeException('No character set defined for CAPTCHA');
        }

        $text = "";
        $size = strlen($string);

        for ($i = 0; $i < $this->length; $i++) {
            $text .= $string[random_int(0, $size - 1)];
        }
        return $text;
    }


/**
 * Add noise effect to the image.
 *
 * @param int   $width   Width of the image.
 * @param int   $height  Height of the image.
 * @param array $options Optional configuration:
 *                       - xRange    : array [min, max] step size for X axis (default: [1, 4])
 *                       - yRange    : array [min, max] step size for Y axis (default: [2, 6])
 *                       - mode      : string one of ['vertical','horizontal','cross','random'] (default: 'random')
 *                       - density   : float 0.0–1.0 noise density, used in 'random' mode (default: 0.3)
 *                       - intensity : float 0.0–1.0 color intensity multiplier (default: 0.5)
 *
 * @return void
 */
protected function addNoise(int $width, int $height, array $options = []): void
{
    // extract options with defaults
    $xRange = $options['xRange'] ?? ($this->difficulty ? [0, 3] : [1, 4]);
    $yRange = $options['yRange'] ?? ($this->difficulty ? [0, 3] : [1, 4]);
    $mode      = $options['mode']      ?? ($this->noiseMode ?? 'cross');
    $density   = $options['density']   ?? ($this->noiseDensity ?? 0.3);
    $intensity = $options['intensity'] ?? ($this->noiseIntensity ?? 1.0);


    // helper for color selection
    $getColor = function () use ($intensity) {
        $clamp = fn($val) => max(0, min(255, intval($val * $intensity)));

        if ($this->noiseColor === 'random') {
            return imagecolorallocate(
                $this->image,
                $clamp(random_int(0, 255)),
                $clamp(random_int(0, 255)),
                $clamp(random_int(0, 255))
            );
        }

        static $fixedColor = null;
        if ($fixedColor === null) {
            list($r, $g, $b) = sscanf($this->noiseColor, "#%02x%02x%02x");
            $fixedColor = imagecolorallocate($this->image, $clamp($r), $clamp($g), $clamp($b));
        }
        return $fixedColor;
    };

    // apply noise based on mode
    switch ($mode) {
        case 'horizontal':
            for ($y = 0; $y < $height; $y += random_int($yRange[0], $yRange[1])) {
                for ($x = 0; $x < $width; $x += random_int($xRange[0], $xRange[1])) {
                    imagesetpixel($this->image, $x, $y, $getColor());
                }
            }
            break;

        case 'cross':
            // vertical lines
            for ($x = 0; $x < $width; $x += random_int($xRange[0], $xRange[1])) {
                for ($y = 0; $y < $height; $y += random_int($yRange[0], $yRange[1])) {
                    imagesetpixel($this->image, $x, $y, $getColor());
                }
            }
            // horizontal lines
            for ($y = 0; $y < $height; $y += random_int($yRange[0], $yRange[1])) {
                for ($x = 0; $x < $width; $x += random_int($xRange[0], $xRange[1])) {
                    imagesetpixel($this->image, $x, $y, $getColor());
                }
            }
            break;

        case 'random':
            $totalPixels = intval($width * $height * $density);
            for ($i = 0; $i < $totalPixels; $i++) {
                $x = random_int(0, $width - 1);
                $y = random_int(0, $height - 1);
                imagesetpixel($this->image, $x, $y, $getColor());
            }
            break;

        case 'vertical':
        default:
            for ($x = 0; $x < $width; $x += random_int($xRange[0], $xRange[1])) {
                for ($y = 0; $y < $height; $y += random_int($yRange[0], $yRange[1])) {
                    imagesetpixel($this->image, $x, $y, $getColor());
                }
            }
            break;
    }
}



    protected function addNoiseLines($width, $height)
    {
        $lines = random_int(3, 4);
        for ($i = 0; $i < $lines; $i++) {
            $x1 = random_int(0, $width );
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width );
            $y2 = random_int(0, $height);
            $color = imagecolorallocate($this->image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagesetthickness($this->image, random_int(1, 3));
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
            $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $attributes .= $attrib . "='" . $safeValue . "' ";
        }
        return "<img src='" . htmlspecialchars($this->captchaGetSrc($config)) . "' " . $attributes . ">";
    }

    public function captchaCheck($input, ?string $token = null): bool
    {
        if ($input === null || $input === false || $input === "" || strlen($input) > 100) {
            return false;
        }

        if ($token) {
            if (empty($token) || strlen($token) !== 64) {
                return false;
            }
            $data = Cache::get('cap_' . $token);
            if (!$data || $data['expire'] < time()) {
                Cache::forget('cap_' . $token);
                return false;
            }
            if ($data['is_math'] ?? false) {
                if (!is_numeric($input)) {
                    Cache::forget('cap_' . $token);
                    return false;
                }
                $inputToCheck = (string)$input;
            } else {
                $inputToCheck = $data['sensitive'] ? $input : strtolower($input);
            }
            $computedHash = hash_hmac('sha256', $inputToCheck, config('app.key'));
            if (hash_equals($data['hash'], $computedHash)) {
                Cache::forget('cap_' . $token);
                return true;
            }
            return false;
        } else {
            $data = $this->session->get('captcha');
            if (!$data || $data['expire'] < time()) {
                $this->session->forget('captcha');
                return false;
            }
            if ($data['is_math'] ?? false) {
                if (!is_numeric($input)) {
                    $this->session->forget('captcha');
                    return false;
                }
                $inputToCheck = (string)$input;
            } else {
                $inputToCheck = $data['sensitive'] ? $input : strtolower($input);
            }
            $computedHash = hash_hmac('sha256', $inputToCheck, config('app.key'));
            if (hash_equals($data['hash'], $computedHash)) {
                $this->session->forget('captcha');
                return true;
            }
            return false;
        }
    }
}