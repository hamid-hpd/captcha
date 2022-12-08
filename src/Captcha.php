<?php

namespace Hpd\Captcha;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Illuminate\Session\Store as Session;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class Captcha
{
    protected  $config;
    protected string $bgColor="#000000";
    protected string $color="#FFFFFF";
    protected string $flakeColor="#FFFFFF";
    protected bool $blur=false;
    protected int $alpha=0;
    protected bool $flake=false;
    protected bool $line=false;
    protected bool $difficult= false;
    protected int $length=5;
    protected int $width=110;
    protected int $height=40;
    protected int $expire=60;
    protected bool $sensitive=false;
    protected array $characters;
    protected bool $lowercase=true;
    protected bool $uppercase=false;
    protected bool $digits=false;
    protected string $fontDir='';
    protected string $font='libre.ttf';
    protected int $fontSize=24;
    protected $str;
    protected $session;
    protected $image;
    public function __construct(Repository $config, Str $str, Session $session){
        $this->config=$config->get('config');
        $this->characters=$config->get('config.characters');
        $this->fontDir = dirname(__DIR__) . '/assets/fonts/';
        $this->str = $str;
        $this->session=$session;
    }
    protected function initial(string $config ){

        foreach($this->config[$config] as $Key =>$value){
            $this->$Key=$value;
        }
    }
    public function create(string $config='default',$is_api=false) {
        $this->initial($config);
        $text=$this->randomString();
        if(!$this->sensitive){
            $codeString=$this->str->lower($text);
        }else{
            $codeString=$text;
        }
        $hash=Hash::make($codeString);
        $this->session->put('captcha',['sensitive'=>$this->sensitive,'hash'=>$hash]);
        if($is_api){
            Cache::put('cap_'.md5($hash),$codeString,$this->expire);
        }
        $this->image=imagecreatetruecolor($this->width,$this->height);
        if($this->bgColor=='random'){
            $bgColor=imagecolorallocate($this->image, rand(100, 255), rand(100, 255), rand(100, 255));
        }else{
            list($br, $bg, $bb) = sscanf($this->bgColor, "#%02x%02x%02x");
            $bgColor=imagecolorallocate($this->image,$br,$bg,$bb);
        }
        imagefill($this->image,0,0,$bgColor);

        $font=$this->fontDir. $this->font;
        $characters=str_split($text);
        if($this->color=='random'){
            for($i=0,$x=5;$i<$this->length;$i++,$x+=20){
                $angel=[mt_rand(3,35),mt_rand(350,360)];
                $y=mt_rand(30,35);
                $textColor=imagecolorallocatealpha($this->image, rand(0, 255), rand(0, 255), rand(0, 255),$this->alpha);
               imagettftext($this->image, $this->fontSize, $angel[mt_rand(0,1)], $x, $y, $textColor, $font, $characters[$i]);
            }
        }else{
            list($cr, $cg, $cb) = sscanf($this->color, "#%02x%02x%02x");
            $textColor = imagecolorallocatealpha($this->image,$cr, $cg, $cb,$this->alpha);
            for($i=0,$x=5;$i<$this->length;$i++,$x+=20){
                $angel=[mt_rand(3,35),mt_rand(350,360)];
                $y=mt_rand(30,35);
                imagettftext($this->image, $this->fontSize, $angel[mt_rand(0,1)], $x, $y, $textColor, $font, $characters[$i]);
            }
        }
        if ($this->difficult){
            $xRange=[0,3];
            $yRange=[0,3];
        }else{
            $xRange=[1,4];
            $yRange=[1,4];
        }
        if($this->blur) {
            imagefilter($this->image, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($this->image, IMG_FILTER_SELECTIVE_BLUR);
        }

        if($this->flake) {
            $this->addFlake($xRange, $yRange);
        }
        if($this->line){
            $this->addLines();
        }
        imagedestroy($this->image);
        return $is_api?
            [
                'code'=>$hash,
                'sensitive'=>$this->sensitive,
                'image'=>'data:image/png;base64,'.$this->createBase64FromImg($this->image)
            ]
            :response(imagepng($this->image), 200, ["Content-type" => "image/png"]);

    }
    protected function randomString():string{
        $string='';
        if($this->lowercase)
            $string.=$this->characters['lowercase'];
        if($this->uppercase)
            $string.=$this->characters['uppercase'];
        if($this->digits)
            $string.=$this->characters['digits'];
        $text ="";

        $size = strlen($string);
        for( $i = 0; $i < $this->length; $i++ ) {
            $text .= $string[ rand( 0, $size - 1 ) ];
        }
        return $text;

    }
  public function createBase64FromImg($imgResource) {
        ob_start();
        imagepng($imgResource);
        $imgData = ob_get_contents ( );
        ob_end_clean ( );
        return base64_encode($imgData);
    }
    protected function addFlake($xRange,$yRange){
        if($this->flakeColor=='random'){
        for($x = 0; $x < $this->width; $x+=mt_rand($xRange[0],$xRange[1])) {
            for ($y = 0; $y < $this->height; $y += mt_rand($yRange[0], $yRange[1])) {
                $flakeColor=imagecolorallocate($this->image, rand(0, 255), rand(0, 255), rand(0, 255));
                imagesetpixel($this->image, $x, $y, $flakeColor);
            }
        }
        }else{
            list($r, $g, $b) = sscanf($this->flakeColor, "#%02x%02x%02x");
            $flakeColor=imagecolorallocate($this->image, $r, $g, $b);
            for($x = 0; $x < $this->width; $x+=mt_rand($xRange[0],$xRange[1])) {
                for ($y = 0; $y < $this->height; $y += mt_rand($yRange[0],$yRange[1])) {
                    imagesetpixel($this->image, $x, $y, $flakeColor);
                }
            }
        }


    }
    protected function addLines(){
        $lines=mt_rand(3,4);
        for ($i = 0; $i < $lines; $i++) {
            $x1=mt_rand(5,$this->width-25);
            $y1=mt_rand(5,$this->height-5);
            $x2=$x1+25;
            $y2=mt_rand($y1,$y1+15);
            $color=imagecolorallocate($this->image, mt_rand(0,255), mt_rand(0,255), mt_rand(0,255));
            imagesetthickness($this->image, rand(2, 3));
            imageline($this->image, $x1, $y1, $x2, $y2, $color);
        }
    }

public function captchaGetSrc(string $config='default'):string{
    return url('captcha/' . $config) . '?' .$this->str->random(8);
}
public function captchaGetImg(string $config='default',array $attribs=[]):string{
    $attributes=' ';
    foreach($attribs as $attrib=>$value){
        if($attrib==='src'){
            continue;
        }
        $attributes.=$attrib."='".$value."' ";

    }
    return "<img src='".$this->captchaGetSrc($config). "'".$attributes.">";
}
public function  captchaCheck(string $value){
    if(!$this->session->has('captcha'))
        return false;
    if(!$this->session->get('captcha.sensitive')){
        $value=$this->str->lower($value);
    }
    $hashedCode=$this->session->get('captcha.hash');
    return Hash::check($value,$hashedCode);
    }
    public function captchaCheckApi(string $value, string $hash,$config='default'){

        if(!Cache::pull('cap_'.md5($hash))){
           return false;
        }else{
            $this->initial($config);
        }

        if(!$this->sensitive){
            $value=$this->str->lower($value);
        }

        return Hash::check($value,$hash);
    }
}
