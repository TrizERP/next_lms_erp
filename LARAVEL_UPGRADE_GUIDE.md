# Laravel Upgrade Guide: 9 → 10 → 11

## Current State
- **Laravel**: v9.52.21
- **PHP**: 8.0+
- **Livewire**: v2.12
- **BotMan**: v2.8

---

## Upgrade Path Summary

### Phase 1: Upgrade to Laravel 10 (Recommended First Step)
| Component | Before | After | Breaking Changes |
|-----------|--------|-------|------------------|
| PHP | ^8.0 | ^8.1 | None significant |
| Laravel | ^9.0 | ^10.0 | Minor |
| Livewire | ^2.12 | ^3.0 | **Yes** |
| Sanctum | ^2.14 | ^3.3 | Minor |
| BotMan | ^2.8 | ^2.10 | Minor |

### Phase 2: Upgrade to Laravel 11 (Optional)
| Component | Before | After | Breaking Changes |
|-----------|--------|-------|------------------|
| PHP | ^8.1 | ^8.2 | None significant |
| Laravel | ^10.0 | ^11.0 | **Yes** |

---

## Phase 1: Laravel 10 Changes

### ✅ Package Updates Applied in `composer.json`

```json
{
    "php": "^8.1",           // Upgraded from ^8.0
    "laravel/framework": "^10.0",
    "laravel/sanctum": "^3.3",
    "livewire/livewire": "^3.0",
    "botman/botman": "^2.10",
    "phpunit/phpunit": "^10.5",
    "spatie/laravel-async": "^2.0"  // New package name
}
```

### ❌ Removed Packages (Deprecated/Integrated)

| Package | Reason | Replacement |
|---------|--------|-------------|
| `beyondcode/laravel-dump-server` | Integrated into Laravel 9.32+ | Use `php artisan dump` |
| `fideloper/proxy` | Integrated into Laravel | Use built-in trusted proxies |
| `generationtux/jwt-artisan` | Deprecated | Use `firebase/php-jwt` directly |
| `google/cloud-language` | Requires update | Use `orhanerday/open-ai` or `textcortex/api` |
| `inspector-apm/inspector-laravel` | Deprecated | Use Laravel Telescope |
| `mews/captcha` | Deprecated | Use `rawilk/laravel-form-components` or custom |
| `spatie/async` | Renamed | Use `spatie/laravel-async` |
| `filp/whoops` | Integrated into Laravel | Built-in exception handler |

---

## Phase 2: Laravel 11 Changes (After Phase 1)

### Major Structural Changes

Laravel 11 introduces a streamlined application structure:

#### 1. Bootstrap Changes
```
# BEFORE (Laravel 10)
bootstrap/app.php        → Creates Application instance
app/Http/Kernel.php      → HTTP Kernel
app/Console/Kernel.php   → Console Kernel
app/Exceptions/Handler.php

# AFTER (Laravel 11)
bootstrap/app.php        → New fluent API with bootstrapWith()
bootstrap/providers.php  → New file for service providers
```

#### 2. Route Changes
```php
// BEFORE: api.php has RouteServiceProvider
Route::middleware('api')
    ->prefix('api')
    ->group(base_path('routes/api.php'));

// AFTER: api.php is auto-loaded
// No RouteServiceProvider needed
```

#### 3. Exception Handler
```php
// BEFORE: app/Exceptions/Handler.php
// AFTER: Configure in bootstrap/app.php
->withExceptions(function (Throwable $exceptions) {
    //
})
```

### Required Changes for Laravel 11

#### 1. Update `bootstrap/app.php`
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Add web middleware here
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

#### 2. Create `bootstrap/providers.php`
```php
<?php

return [
    App\Providers\AppServiceProvider::class,
];
```

#### 3. Update `config/app.php`
Remove provider registration (now in `bootstrap/providers.php`)

---

## Breaking Changes Checklist

### Livewire 2 → 3 Breaking Changes

#### 1. Component Registration
```php
// BEFORE (Livewire 2)
#[Livewire\Component]
class MyComponent extends Component
{
}

// AFTER (Livewire 3)
class MyComponent extends Component
{
    #[Layout('layouts.app')]
    public $title = 'My Page';
}
```

#### 2. Route Registration
```php
// BEFORE (Livewire 2)
Route::livewire('/post/{id}', 'show-post');

// AFTER (Livewire 3)
use App\Livewire\ShowPost;
Route::get('/post/{id}', ShowPost::class);
```

#### 3. Property Naming
```php
// AFTER (Livewire 3)
// All properties are now public by default
// Use #[Rule] attribute for validation
use Livewire\Attributes\Rule;

class MyComponent extends Component
{
    #[Rule('required|min:3')]
    public $name = '';
}
```

#### 4. Lifecycle Hooks
```php
// BEFORE
public function updated($field)
public function hydrate()
public function dehydrate($data)

// AFTER (Livewire 3)
public function updated(string $field, mixed $value)
public function hydrate()
public function dehydrate($data, $context)
```

#### 5. Mount Method
```php
// BEFORE
public function mount($userId)
{
    $this->userId = $userId;
}

// AFTER
public function mount(int $userId): void
{
    $this->userId = $userId;
}
```

### Sanctum Changes (2 → 3)

```php
// config/sanctum.php - no longer needed
// Use API tokens via HasApiTokens trait

// In User model
use Laravel\Sanctum\HasApiTokens;

// Route middleware
'auth:sanctum'  // Still works
```

### BotMan Changes (2.8 → 2.10)

```php
// BotMan configuration is mostly compatible
// Update config/botman/config.php

return [
    'conversation_cache_time' => 30,  // Changed from minutes to seconds
    'botman' => [
        'web' => [
            'matching_exact' => false,  // New option
        ],
    ],
];
```

---

## AI/LLM Package Updates for Laravel 11

### OpenAI Client Upgrade
```bash
# Remove old package
composer remove openai-php/client

# Install new SDK
composer require openai-php/laravel
```

Or use direct API calls with Guzzle:
```php
// app/Services/OpenAIService.php - Update to use API v2
public function callOpenAI(string $prompt): array
{
    $response = Http::withToken(env('OPENAI_API_KEY'))
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

    return $response->json();
}
```

### Gemini API Update
```bash
composer require google/generative-ai-php
```

---

## Step-by-Step Upgrade Commands

### Phase 1: Upgrade to Laravel 10

```bash
# 1. Backup your project
git checkout -b upgrade/laravel-10
cp -r .env .env.backup

# 2. Update composer.json (already done)

# 3. Clear cache
rm -rf bootstrap/cache/*.php
rm -rf vendor
rm composer.lock

# 4. Update dependencies
composer install --no-interaction

# 5. Run migrations (if any)
php artisan migrate

# 6. Update Livewire components
# See Livewire 3 migration guide below

# 7. Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 8. Test your application
php artisan serve
```

### Phase 2: Upgrade to Laravel 11

```bash
# 1. Create new branch
git checkout -b upgrade/laravel-11

# 2. Update composer.json
composer require laravel/framework:^11.0 --no-interaction

# 3. Update bootstrap/app.php (see template above)

# 4. Create bootstrap/providers.php

# 5. Remove deprecated files
rm -f app/Http/Kernel.php
rm -f app/Console/Kernel.php
rm -f app/Exceptions/Handler.php
rm -f app/Providers/RouteServiceProvider.php

# 6. Update routes/api.php
# Remove middleware group wrapper

# 7. Clear and rebuild
php artisan about
php artisan route:list
```

---

## Files That Need Updates

### Livewire Components to Update

```bash
# Find all Livewire components
find app -name "*.php" -exec grep -l "extends Component" {} \; | xargs grep -l "Livewire"
```

Update each component for Livewire 3 syntax.

### Route Files to Update

| File | Change Required |
|------|-----------------|
| `routes/api.php` | Remove `Route::middleware('api')` group |
| `routes/web.php` | Remove `Route::middleware('web')` group |
| `routes/channels.php` | Update for Laravel 11 |

### Config Files to Review

```bash
# Check for deprecated config keys
php artisan about | grep -i deprecat
```

---

## Testing Checklist

```bash
# 1. Run tests
php artisan test

# 2. Check routes
php artisan route:list

# 3. Check AI services
php artisan tinker
>>> app(App\Services\OpenAIService::class)->generateTitleAndDescription('Test', 'Chapter', 'Subject', 'Standard')

# 4. Test BotMan
>>> app('botman')-> listens();

# 5. Check PAL services
>>> app(App\Services\PAL\AI\AIOrchestrationService::class)
```

---

## Rollback Plan

```bash
# If upgrade fails
git checkout main
rm -rf vendor composer.lock
composer install
```

---

## Need Help?

- **Laravel Upgrade Docs**: https://laravel.com/docs/10.x/upgrade
- **Livewire 3 Migration**: https://livewire.laravel.com/docs/upgrading
- **Laravel 11 Upgrade**: https://laravel.com/docs/11.x/upgrade
