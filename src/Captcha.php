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
    protected int $alpha = 0; // (0 = fully opaque, 127 = fully transparent)
    protected bool $noise = false;
    protected string $noiseMode = 'cross';//'vertical','horizontal','cross','random'
    protected float $noiseDensity = 0.1;// 0 -> 1.0
    protected float $noiseIntensity = 1.0;// 0 -> 1.0
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
        $this->config = $config->get('hpd_captcha');
        $this->characters = $this->characters = $this->config['characters'] ?? []; 
        $this->fontDir = realpath(dirname(__DIR__) . '/resources/assets/fonts/') . '/';
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

        $expression = "$num1 $operation $num2 = ?";
        return [
            'expression' => $expression,
            'result' => $result
        ];
    }

    // New method for generating word puzzle
    protected function generateWordPuzzle(): array
    {
        $words = $this->loadFile('data','words_en.php') ;

        if (empty($words)) {
            throw new \RuntimeException('CAPTCHA words list is empty.');
        }

        // Filter words to 4-6 letters
        $filteredWords = array_filter($words, function ($word) {
            $len = strlen($word);
            return $len >= 4 && $len <= 7 && ctype_alpha($word);
        });

        if (empty($filteredWords)) {
            throw new \RuntimeException('No valid 4-7 letter words found in the words list.');
        }

        $word = $filteredWords[array_rand($filteredWords)];
        $original = $word;
        $length = strlen($word);

        $toRemove = ($length === 4) ? 1 : random_int(1, 2);

        $positions = [];
        $available = range(1, $length - 2); // middle letters only (avoid first and last)
        shuffle($available);

        for ($i = 0; $i < $toRemove && $i < count($available); $i++) {
            $positions[] = $available[$i];
        }
        sort($positions);

        // Build display with '_' for missing letters
        $display = '';
        $posIndex = 0;
        for ($i = 0; $i < $length; $i++) {
            if ($posIndex < count($positions) && $i === $positions[$posIndex]) {
                $display .= '_';
                $posIndex++;
            } else {
                $display .= $word[$i];
            }
        }

        return [
            'display' => $display,
            'answer' => $original
        ];
    }

    public function create(string $config = 'default', $is_api = false)
    {
        $this->initial($config);
        $isMathCaptcha = $this->type === 'math';
        $isWordCaptcha = $this->type === 'word_puzzle';

        if ($isMathCaptcha) {
            $mathData = $this->generateMathExpression();
            $text = $mathData['expression'];
            $codeString = (string)$mathData['result'];
        } elseif ($isWordCaptcha) {
            $wordData = $this->generateWordPuzzle();
            $text = $wordData['display'];
            $codeString = $this->sensitive ? $wordData['answer'] : strtolower($wordData['answer']);
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
            'is_math' => $isMathCaptcha,
            'is_word_puzzle' => $isWordCaptcha
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

        // Split text into characters for rendering (supports 'multi' color for all types)
        $characters = str_split($text);
        $numChars = count($characters);

        // Calculate approximate bbox using angle 0 for sizing
        $bbox = imagettfbbox($this->fontSize, 0, $font, $text);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $textHeight = abs($bbox[7] - $bbox[1]);

        $padding = 10;
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

        // Pre-allocate fixed color if not 'multi' or 'random' per char
        $fixedTextColor = null;
        if ($this->color !== 'multi' && $this->color !== 'random') {
            list($cr, $cg, $cb) = sscanf($this->color, "#%02x%02x%02x");
            $fixedTextColor = imagecolorallocatealpha($this->image, $cr, $cg, $cb, $this->alpha);
        }

        // Center text
        $charWidth = $textWidth / $numChars; // Approximate per char
        $startX = ($imageWidth - $textWidth) / 2;
        $y = ($imageHeight + $textHeight) / 2;

        // Render each character individually (for multi-color support)
        for ($i = 0; $i < $numChars; $i++) {
            // Angle: random per char for string type, fixed 0 for math/word
            $angle = ($isMathCaptcha || $isWordCaptcha) ? 0 : (random_int(0, 1) ? random_int(3, 35) : random_int(350, 360));

            // Color: multi/random per char, or fixed
            if ($this->color === 'multi' || $this->color === 'random') {
                $textColor = imagecolorallocatealpha($this->image, random_int(0, 255), random_int(0, 255), random_int(0, 255), $this->alpha);
            } else {
                $textColor = $fixedTextColor;
            }

            imagettftext($this->image, $this->fontSize, $angle, $startX + $i * $charWidth, $y, $textColor, $font, $characters[$i]);
        }
        if ($this->blur) {
            imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        if ($this->noise) {
            $this->addNoise($imageWidth, $imageHeight);
        }
        if ($this->noiseLines) {
            $this->addNoiseLines($imageWidth, $imageHeight);
        }

        ob_start();
        imagepng($this->image);
        $imgData = ob_get_contents();
        ob_end_clean();
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
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $color = imagecolorallocate($this->image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagesetthickness($this->image, random_int(1, 3));
            imageline($this->image, $x1, $y1, $x2, $y2, $color);
        }
    }

    public function captchaGetSrc(string $config = 'default'): string
    {
        return url('hpd/captcha/' . $config) . '?' . $this->str->random(8);
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
            } elseif ($data['is_word_puzzle'] ?? false) {
                $inputToCheck = $data['sensitive'] ? $input : strtolower($input);
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
            } elseif ($data['is_word_puzzle'] ?? false) {
                $inputToCheck = $data['sensitive'] ? $input : strtolower($input);
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
protected function loadFile($dir,$fileName)
{
    $published = storage_path("app/hpd/captcha/{$fileName}");
    $package   = __DIR__ . "/../resources/{$dir}/{$fileName}";

    return include (file_exists($published) ? $published : $package);
}

}