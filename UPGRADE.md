# Upgrade Guide

## Upgrade Guide: From v1.x or v2.x to v3.0

This guide helps you upgrade the **hpd/captcha** package from versions **1.x** or **2.x** to **3.0**.  
Version 3.0 introduces new features such as **word_puzzle captcha** and **multi‑color support**, along with a few breaking changes that require manual adjustments.

---

### 1. Update the Package via Composer

Update your project `composer.json` :

```json
"hpd/captcha": "^3.0"
```

Then run:

```bash
composer update hpd/captcha
```

---

### 2. Re‑publish the Configuration File (**Important**)

#### What changed?
- In previous versions, the config file was published as:
  ```
  config/captcha.php
  ```
- In v3.0, the config file is now:
  ```
  config/hpd_captcha.php
  ```

#### Required steps:

1. **Delete or back up** your old config file (if it exists).
2. Publish the new configuration file:

```bash
php artisan vendor:publish --tag=hpd-captcha-config --force
```

3. Copy your previous settings (e.g. `length`, `characters`, `bgColor`, `color`, `type`, etc.) into the new `hpd_captcha.php` file.

#### Optional – Custom word list (word_puzzle)

If you want to customize the word list used by the `word_puzzle` captcha:

```bash
php artisan vendor:publish --tag=hpd-captcha-words
```

> **Note:**  
> If no custom config or word list is published, the package will automatically fall back to its internal defaults.

---

### 3. Route Changes

In previous versions, routes were **not prefixed**:

```
/captcha/{config?}
/captcha/api/{config?}
```

In v3.0, **all routes are prefixed with `/hpd`**:

- Web route:
  ```
  /hpd/captcha/{config?}
  ```

- API route:
  ```
  /hpd/captcha/api/{config?}
  ```

#### Required actions:

- Update any hardcoded URLs in:
  - Blade templates
  - JavaScript files
  - API calls

- Helper functions such as:
  - `captcha_get_src('default')`
  - `captcha_get_api_src('default')`

  automatically use the correct prefix in v3.0 and usually **require no changes**.

#### Example (JavaScript)

```js
// Before
fetch('/captcha/api/' + config);

// After
fetch('/hpd/captcha/api/' + config);
```

---

### 4. New Features (Optional but Recommended)

#### 🎨 Multi‑color Text
Enable random multi‑color text:

```php
'color' => 'multi'
```

Works with all captcha types.

#### 🧩 Word Puzzle Captcha
Enable word puzzles such as `ap_le`:

```php
'type' => 'word_puzzle'
```

---

### 5. Notes for Upgrading from v2.x

- The noise method changes introduced in v2 remain the same.
- No additional breaking changes exist beyond what is documented above.

---

### 6. Testing After Upgrade

Clear all caches:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

Then test your forms or API endpoints to ensure the captcha loads and validates correctly.

---

If you encounter any issues, please check the logs or open an issue on the GitHub repository.
