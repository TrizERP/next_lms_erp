<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
     */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
     */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
     */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
     */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
     */

    'timezone' => 'Asia/Kolkata',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
     */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
     */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
     */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
     */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
     */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Package Service Providers...
         */
        GenTux\Jwt\Support\LaravelServiceProvider::class,
        Mews\Captcha\CaptchaServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

        Yajra\DataTables\DataTablesServiceProvider::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
     */

    'aliases' => Facade::defaultAliases()->merge([
        'Captcha' => Mews\Captcha\Facades\Captcha::class,
        'DataTables' => Yajra\DataTables\Facades\DataTables::class,
    ])->toArray(),

    'debug_blacklist' => [
        '_ENV' => [
            'APP_KEY',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'MAIL_PASSWORD',
            'PUSHER_APP_KEY',
            'PUSHER_APP_SECRET',
            'SERVER_NAME',
            'SERVER_ADDR',
            'SERVER_PORT',
            'REMOTE_ADDR',
            'DOCUMENT_ROOT',
            'SCRIPT_FILENAME',
            'CONTEXT_DOCUMENT_ROOT',
            'SERVER_ADMIN',
            'REQUEST_URI',
            'SCRIPT_NAME',
            'PHP_SELF',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'MAIL_HOST',
            'MAIL_DRIVER',
            'MAIL_PORT',
            'MAIL_USERNAME',
            'HTTP_HOST',
            'SERVER_SOFTWARE',
            'REMOTE_PORT',
            'REDIRECT_URL',
            'REDIS_HOST',
            'REDIS_PORT',
            'SESSION_LIFETIME',
            'JWT_SECRET',
            'JWT_ALGO',
            'JWT_LEEWAY',
            'JWT_INPUT',
            'JWT_HEADER',
            'REQUEST_SCHEME',
            'GATEWAY_INTERFACE',
            'SERVER_PROTOCOL',
            'REQUEST_METHOD',
            'REQUEST_TIME_FLOAT',
            'QUERY_STRING',
            'REQUEST_TIME',
            'APP_NAME',
            'APP_ENV',
            'APP_URL',
            'LOG_CHANNEL',
            'APP_DEBUG',
            'BROADCAST_DRIVER',
            'CACHE_DRIVER',
            'QUEUE_CONNECTION',
            'SESSION_DRIVER',
            'MAIL_ENCRYPTION',
            'PUSHER_APP_CLUSTER',
            'MIX_PUSHER_APP_CLUSTER',
            'REDIRECT_UNIQUE_ID',
            'UNIQUE_ID',
            'HTTP_CONNECTION',
            'HTTP_CACHE_CONTROL',
            'HTTP_UPGRADE_INSECURE_REQUESTS',
            'HTTP_USER_AGENT',
            'HTTP_ACCEPT',
            'HTTP_ACCEPT_ENCODING',
            'HTTP_ACCEPT_LANGUAGE',
            'HTTP_COOKIE',
            'PATH',
            'HTTP_REFERER',
            'REDIRECT_QUERY_STRING',

        ],
        '_SERVER' => [
            'APP_KEY',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'MAIL_PASSWORD',
            'PUSHER_APP_KEY',
            'PUSHER_APP_SECRET',
            'SERVER_NAME',
            'SERVER_ADDR',
            'SERVER_PORT',
            'REMOTE_ADDR',
            'DOCUMENT_ROOT',
            'SCRIPT_FILENAME',
            'CONTEXT_DOCUMENT_ROOT',
            'SERVER_ADMIN',
            'REQUEST_URI',
            'SCRIPT_NAME',
            'PHP_SELF',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'MAIL_HOST',
            'MAIL_DRIVER',
            'MAIL_PORT',
            'MAIL_USERNAME',
            'HTTP_HOST',
            'SERVER_SOFTWARE',
            'REMOTE_PORT',
            'REDIRECT_URL',
            'REDIS_PORT',
            'REDIS_HOST',
            'SESSION_LIFETIME',
            'JWT_SECRET',
            'JWT_ALGO',
            'JWT_LEEWAY',
            'JWT_INPUT',
            'JWT_HEADER',
            'REQUEST_SCHEME',
            'GATEWAY_INTERFACE',
            'SERVER_PROTOCOL',
            'REQUEST_METHOD',
            'REQUEST_TIME_FLOAT',
            'QUERY_STRING',
            'REQUEST_TIME',
            'APP_NAME',
            'APP_ENV',
            'APP_URL',
            'LOG_CHANNEL',
            'APP_DEBUG',
            'BROADCAST_DRIVER',
            'CACHE_DRIVER',
            'QUEUE_CONNECTION',
            'SESSION_DRIVER',
            'MAIL_ENCRYPTION',
            'PUSHER_APP_CLUSTER',
            'MIX_PUSHER_APP_CLUSTER',
            'REDIRECT_UNIQUE_ID',
            'UNIQUE_ID',
            'HTTP_CONNECTION',
            'HTTP_CACHE_CONTROL',
            'HTTP_UPGRADE_INSECURE_REQUESTS',
            'HTTP_USER_AGENT',
            'HTTP_ACCEPT',
            'HTTP_ACCEPT_ENCODING',
            'HTTP_ACCEPT_LANGUAGE',
            'HTTP_COOKIE',
            'PATH',
            'HTTP_REFERER',
            'REDIRECT_QUERY_STRING',

        ],
        '_POST' => [
            'password',
        ],
    ],

];
