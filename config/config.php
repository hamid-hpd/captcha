<?php
return [
    'disable' => env('CAPTCHA_DISABLE', !str_contains(env('APP_ENV', 'local'), 'prod')),
    'characters'=>[
        'lowercase'=>'abxefghijklymcnopsqrtuvd',
        'uppercase'=>'AXOBEPFCYDGWSJKZHIRULMNQTV',
        'digits'=>'6302581497'
    ],
    'default' => [
        'length' => 5,
        'bgColor'=>'#FFFFFF',
        'color'=>'random',
        'flake'=>true,
        'flakeColor'=>'#BBC6C8',
        'sensitive'=>false,
        'digits'=>true,
        'uppercase'=>true,
        'lowercase'=>true,
        'alpha'=>10,
        'blur'=>true
    ],
    'easy'=>[
        'length' => 5,
        'bgColor'=>'#FFFFFF',
        'color'=>'random',
        'blur'=>true,
        'alpha'=>25,
    ],
    'easyDark'=>[
        'length' => 5,
        'bgColor'=>'#000000',
        'color'=>'random',
        'blur'=>true,
        'alpha'=>75
    ],
    'random'=>[
        'bgColor'=>'random',
        'color'=>'random',
        'flake'=>true,
        'flakeColor'=>'random'
    ],
    'light' => [
        'bgColor' => '#FFFFFF',
        'color' => "#000000",
        'flake'=>true,
        'flakeColor'=>"#000000",
    ],
    'dark' => [
        'bgColor' => "#000000",
        'color' => '#FFFFFF',
        'flake'=>true,
        'flakeColor'=>"#FFFFFF",
    ],
    'mini' => [
        'length'=>3,
        'width'=>75,
        'bgColor'=>'#FFFFFF',
        'color'=>"random",
        'flake'=>true,
        'flakeColor'=>"random",
        'blur'=>true
    ],
    'diffi'=>[
        'difficult'=> true,
        'font'=>'libre.ttf',
        'uppercase'=>true,
        'sensitive'=>true,
        'digits'=>true,
        'fontSize'=>22,
        'color'=>"#FFFFFF",
        'flake'=>true,
        'flakeColor'=>"#FFFFFF",
    ],
    'cap'=>[
        'uppercase'=>true,
        'lowercase'=>false,
        'digits'=>false,
        'width'=>120,
        'bgColor' => '#FFFFFF',
        'color' => "random",
        'flake'=>true,
        'flakeColor'=>"random",

    ]
];
