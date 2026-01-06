# Captcha for Laravel

_A Lightweight CAPTCHA Solution for Laravel_

Maintained by [Hamid HpD](https://github.com/hamid-hpd)

## Preview
![Preview](./docs/images/preview.png)

## Table of Contents
- [Introduction](#introduction)
  - [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
  - [Properties](#properties)
- [How to use](#how-to-use)
  - [Session mode](#session-mode)
    - [Example](#example)
  - [API mode](#api-mode)
    - [Example](#example-1)
- [What's New](#whats-new)
  - [Version 3.0.0](#version-300)
  - [Changelog](#changelog)

- [License](#license)   
- [Other Proiects](#check-out-my-other-projects) 

## Introduction
🛡️ HPD Captcha — Simple, Secure & Laravel Friendly
- A lightweight, privacy-friendly CAPTCHA package for Laravel — no external services, no API keys, no tracking.

### ✨ Features

✅ Works fully offline (no external API)

✅ Simple image-based CAPTCHA

✅ Easy integration with Laravel forms

✅ Supports Laravel 8.x → 12.x

✅ Customizable appearance & difficulty

✅ No tracking, no cookies, no external requests

✅ Lightweight & fast


---

## ⚠️ Important Notice

> **Version 1.x of HPD Captcha is officially deprecated.**  
> All new projects should use **v3.x**.  
> Upgrading from v1/v2? Check the [Upgrade Guide](./UPGRADE.md).

---

## Installation

Require this package with composer:
```
composer require hpd/captcha
```

## Usage

It doesn't need to add CaptchaServiceProvider to the providers array in config/app.php.


## Configuration

To use your own settings, first publish the config file:


```bash
php artisan vendor:publish --tag=hpd-captcha-config
```
Then customize configuration properties as you like.

```php
return [
    'disable' => env('CAPTCHA_DISABLE', env('APP_ENV') !== 'production'),
    'characters' => [
        'lowercase' => 'abdefghjklmnpqrstuvwxyz', 
        'uppercase' => 'ABCDEFGHJKLMNPQRSTUVWXYZ', 
        'digits' => '23456789' // 
    ],
    'default' => [
        'length' => 5,
        'bgColor' => '#FFFFFF',
        'color' => 'multi',
        'noiseLines' => true,
        'noise' => true,
        'noiseColor' => '#FFFFFF',
        'sensitive' => false,
        'digits' => true,
        'uppercase' => true,
        'lowercase' => true,
        'alpha' => 10,
        'blur' => true,
    ],
    ...
];
```
### Optional Words File Publishing (version >=3.0.)

The package comes with a built-in words file (resources/data/words_en.php) used for CAPTCHA generation, so it works immediately without any setup.

If you want to customize the words list, you can publish it to your application:

```bash
php artisan vendor:publish --tag=hpd-captcha-words
```

### Properties
The following properties are customizable from published config.php file.
```php
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

```

## How to use

### Session mode
Session mode is designed for traditional web forms.
The captcha solution is stored in the server-side session and validated against the user’s input when the form is submitted

You can use the following helper functions in your project to get a Captcha image.
```php
    captcha(); // returns image
    
    captcha_get_src()// returns image source(URl)

    captcha_get_html()// returns img html element
```
Pass the configuration name to the function. If omitted, the 'default' configuration is
```php
    captcha('default'); // returns image
    
    captcha_get_src('easy')// returns image source(URl)

    captcha_get_img('dark')// returns img element
```
#### Example

Get Captcha image src:
```php
    <img src="{{!! captcha_get_src()!!}}" titile="Captcha" alt="Captcha">
```
Get img element:
```php
    <div> {{!! captcha_get_img()!!}}</div>
```
Validation

```php

    Route::post('captcha_check', function() {
         $validator = Validator::make($request->all(), [
                'captcha' => 'required|captcha',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }
   
 });
```
### API mode 

API mode is designed for single-page applications (SPA), mobile apps, or any frontend that communicates with the backend through JSON APIs.
Instead of sessions, the captcha state is temporarily stored in the cache. A captcha_token is returned and must be included in subsequent requests.
```php
     [
        'token'=>'816fc4e459eb3bd240a58beee41e723df2d1b1f29300f2a7920cdc372f728695' 
        ,
        'image'=>'data:image/png;base64,'.$this->createBase64FromImg($this->image)
     ]
```
#### Example
```js
        async function loadCaptcha(config='default') {
        try {
        const response = await fetch(`hpd/captcha/api/${config}`);
        const data = await response.json();
    
        const img = document.createElement("img");
        img.src = data.image; 
        document.getElementById("captcha-container").appendChild(img);
        document.getElementById('captcha_token').value = data.token;
    
    } catch (error) {
        console.error('CAPTCHA loading failed:', error);
    }
}

// Call the function
loadCaptcha('math');
```
Validation

```php
    $validator = validator()->make(request()->all(),
        ['captcha' => 'required|captcha_api:'. request('captcha_token')
       
        ];
    );
    if ($validator->fails()) {
        return response()->json([
            'captcha' => 'Invalid captcha',
        ],422);

    } else {
        // continue
    }
```
## What's New

### Version 3.0.0 

- **Added `word_puzzle` type configuration option**

    Now you can generate captcha challenges using incomplete English words from a dictionary. Users see a word with missing letters (e.g., ap_le) and must type the complete word (apple).
    ```php
    <div> {!!captcha_get_img('word_puzzle_default')!!}</div>
    ```
- **From now on, the `color` property in all types supports the `multi` option.**

- **⚠️ Updated Routes with `hpd` Prefix**

     All captcha routes have been updated to include a hpd prefix for better organization and avoiding naming conflicts with other CAPTCHA packages.
     - Web routes now use:
        ```arduino
        hpd/captcha/{config?}
        ```
     - API routes now use:
        ```php
        hpd/captcha/api/{config?}
        ```   

- **All published config files use the `hpd_` prefix to:**
    - **Avoid conflicts** with other CAPTCHA packages
    - **Unique identification** for HPD Captcha package
    - **Easy management** in projects with multiple packages

    | Package File |    Published As   |      Purpose     |
    |--------------|-------------------|------------------|
    | `config.php` | `hpd_captcha.php` | Main CAPTCHA settings & themes |
    
 - **All data files, such as the words file, will be placed in the address `storage\hpd\captcha\words_en.php` after publishing.**

- **Smart detection of published vs. package config and data files with seamless fallback.**

- **Refactored package directories structure** 

### Changelog 

Checkout the [CHANGELOG](./CHANGELOG.md) for details on updates.

## License

This project is licensed under the [MIT License](./LICENSE).



## Check Out My Other Projects

Hey! If you liked this package, you might enjoy some of my other work too:

- [Validatify](https://github.com/hamid-hpd/validatify.git) – A PHP input validation library.

I'm always building and sharing new stuff — feel free to take a look, star ⭐ what you like, or even open a PR!
