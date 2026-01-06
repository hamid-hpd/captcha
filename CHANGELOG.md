# Changelog

All notable changes to this project will be documented in this file.

## [3.0.0] - 2026-12-18

### Added
- New `word_puzzle` CAPTCHA type: users complete incomplete English words from a built-in dictionary (e.g., `ap_le` → `apple`).
  ```php
  {!! captcha_get_img('word_puzzle_default') !!}
  ```
 - Support for **`multi`** option in the color property across all CAPTCHA types.
 
 ### Changed
- Breaking: All routes now prefixed with hpd/ to prevent naming conflicts:
    - Web route: hpd/captcha/{config?}
    - API route: hpd/captcha/api/{config?}

- Breaking: Published config file is now `hpd_captcha.php` (previously captcha.php).
- All published data files (e.g., words list) now use `hpd_ prefix` and are stored in `storage/hpd/captcha/`.
- Improved smart detection with seamless fallback between published custom files and built-in package defaults.
- Refactored internal package directory structure for better maintainability and organization.

## [2.0.0] - 2025-08-29

### Added

New math CAPTCHA type: simple math challenges (e.g., 3 + 5 = ?).

### Changed

- Optimized existing configuration options for improved performance and easier customization.
- Refactored internal codebase to enhance security, readability, and maintainability.
- Enhanced noise generation with detailed customization options via the addNoise method.

### Fixed

Several bugs and stability issues for a smoother user experience.

### Noise Customization Details
The addNoise method now accepts two required arguments (width, height) and an optional associative array for fine-grained control:

| Option       | Type             | Default (if not provided)         | Description |
|--------------|------------------|-----------------------------------|-------------|
| **`xRange`** | `array[int,int]` | Determined automatically based on `difficulty` | Specifies the horizontal step size for applying noise. The first value is the minimum step, and the second is the maximum step. Smaller steps result in denser noise horizontally. |
| **`yRange`** | `array[int,int]` | Determined automatically based on `difficulty` | Specifies the vertical step size for applying noise. The first value is the minimum step, and the second is the maximum step. Smaller steps result in denser noise vertically. |
| **`mode`**   | `string`         | `'cross'`                        | Determines how the noise is applied: <br>• `'vertical'` → vertical lines of noise <br>• `'horizontal'` → horizontal lines of noise <br>• `'cross'` → both vertical and horizontal lines <br>• `'random'` → randomly scattered pixels |
| **`density`** | `float`         | `0.1`                             | Used only when `mode` is `'random'`. Defines the fraction of total image pixels that will contain noise. Must be between `0.0` and `1.0`. |
| **`intensity`** | `float`       | `1.0`                             | Multiplier for the color intensity of the noise. `1.0` means full brightness, `0.0` results in black. This can make the noise lighter or darker. |

---

#### Example Usage
```php
// 1. Default noise based on difficulty
$this->addNoise(200, 100);

// 2. Only change mode
$this->addNoise(200, 100, ['mode' => 'cross']);

// 3. Override xRange and yRange manually
$this->addNoise(200, 100, [
    'xRange'   => [2, 6],
    'yRange'   => [3, 7],
    'mode'     => 'horizontal',
    'density'  => 0.4,//or $this->noiseDensity
    'intensity'=> 0.7 //or $this->noiseIndensity
]);
```
## [1.x.x] - Deprecated

`Version 1.x` is officially **`deprecated`**.

All users are encouraged to upgrade to `v3.x`.

Refer to the [UPGRADE](./UPGRADE.md) guide for migration instructions.