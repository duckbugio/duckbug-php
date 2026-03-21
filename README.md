# Official DuckBug SDK for PHP

Duck is a developer-focused SDK for capturing PHP errors and logs. It keeps one public capture API for application code, builds one canonical DuckBug event model in core, and can fan that event out to one or more providers.

## 🚀 Quick Start

### Install

Install the SDK using [Composer](https://getcomposer.org/).

```bash
composer require duckbug/duckbug
```

`duckbug/duckbug` remains the compatibility/full package and stays compatible with legacy projects on PHP `>= 7.1`.

If you want narrower framework-specific dependencies, install split packages instead:

```bash
composer require duckbug/duckbug-core
composer require duckbug/duckbug-symfony
composer require duckbug/duckbug-laravel
composer require duckbug/duckbug-yii2
composer require duckbug/duckbug-slim4
composer require duckbug/duckbug-wordpress
composer require duckbug/duckbug-drupal
composer require duckbug/duckbug-drupal7
```

In split mode, `duckbug/duckbug-core` contains the SDK runtime, while framework packages only ship their own adapters on top of the same `DuckBug\...` namespaces.

Core remains event-first: application code calls `Duck::quack()` / `Duck::log()`, core builds a canonical `Event`, and providers receive that event for delivery to DuckBug, Telegram, or another target.

### Configuration

Initialize the SDK as early as possible in your application

```php
$duck = \DuckBug\Duck::wake([
    new \DuckBug\Core\ProviderSetup(
        \DuckBug\Providers\DuckBugProvider::create(
            '__PUBLIC_DSN__',
            false, // env logging
            true,  // request context logging
            5,     // timeout
            3,     // connection timeout
            10     // batch size
        ),
        true, // enable catching Throwable
        false // disable Debug level logs
    )
]);

$duck
    ->setRelease('checkout@1.2.3')
    ->setEnvironment('production')
    ->setServerName(gethostname() ?: 'unknown')
    ->setService('checkout')
    ->setTag('module', 'payments')
    ->setUser(['id' => 42, 'email' => 'user@example.com']);
```

### Usage

#### Log exceptions using `quack()`

```php
try {
    throw new \Exception('Quack quack');
} catch (\Exception $exception) {
    \DuckBug\Duck::get()->quack($exception);
}
```

#### Log messages with severity levels

```php
\DuckBug\Duck::get()
    ->addBreadcrumb(['message' => 'Starting checkout', 'category' => 'app'])
    ->warning('User not found', ['userId' => 8]);
```

### Manual metadata and tracing context

```php
\DuckBug\Duck::get()
    ->setRequestId('req-123')
    ->setTransaction('POST /checkout')
    ->setTrace('trace-123', 'span-456')
    ->setFingerprint('checkout:payment-timeout');
```

### Privacy controls and delivery hooks

```php
$provider = \DuckBug\Providers\DuckBugProvider::create('__PUBLIC_DSN__')
    ->configurePrivacy([
        'headers' => false,
        'body' => false,
        'cookies' => false,
        'session' => false,
        'env' => false,
    ])
    ->setBeforeSend(function (array $payload) {
        unset($payload['user']['email']);

        return $payload;
    })
    ->setTransportFailureHandler(function (string $type, array $items, $result, string $message): void {
        error_log($message);
    });

\DuckBug\Duck::wake([
    new \DuckBug\Core\ProviderSetup($provider),
]);
```

`configurePrivacy()` disables whole request/env sections before sending, while `setBeforeSend()` lets you redact or drop a single event at the last moment.

### Transactions and spans

```php
$transaction = \DuckBug\Duck::get()->startTransaction('POST /checkout', 'http.server');

$paymentSpan = $transaction->startChild('payment.charge', 'Charge card');
$paymentSpan->setData(['gateway' => 'stripe']);
$paymentSpan->finish('ok');

$transaction
    ->addMeasurement('db.query.count', 4)
    ->finish('ok');

\DuckBug\Duck::get()->captureTransaction($transaction);
```

### Flush buffered events

If you use batching, flush explicitly before the process exits in long-running workers:

```php
\DuckBug\Duck::get()->flush();
```

## ⚙️ Custom and Multiple Providers

#### Create your own Provider

```php
use DuckBug\Core\Event;
use DuckBug\Core\Provider;

class MyCustomProvider implements Provider
{
    public function captureEvent(Event $event): void
    {
        $payload = $event->getPayload();

        // Send the canonical DuckBug event to your own target.
        error_log('[MyCustomProvider] ' . $event->getType() . ' ' . json_encode($payload));
    }
}
```

#### Initialize Duck with Multiple Providers

```php
\DuckBug\Duck::wake([
    new \DuckBug\Core\ProviderSetup(\DuckBug\Providers\DuckBugProvider::create('__PUBLIC_DSN__')),
    new \DuckBug\Core\ProviderSetup(new MyCustomProvider())
]);
```

## 🕵️ Pond

Duck also supports gathering request-specific context information such as IP address, URL, query/body parameters, headers, and more via `Pond`.
When you implement a custom `Provider`, you usually do not need to call `Pond` directly because core already enriches the canonical `Event` before the provider receives it.

Sensitive headers, cookies, session values and nested payload fields are scrubbed by default.

#### Example with custom Provider

```php
use DuckBug\Core\Event;
use DuckBug\Core\Provider;

class MyCustomProvider implements Provider
{
    public function captureEvent(Event $event): void
    {
        $payload = $event->getPayload();

        // The payload already includes request/runtime metadata collected by core.
        error_log('[MyCustomProvider] ' . $payload['message'] . ' ' . json_encode($payload));
    }
}
```

## 🔌 Integrations

### Global PHP handlers

```php
$duck = \DuckBug\Duck::wake([
    new \DuckBug\Core\ProviderSetup(
        \DuckBug\Providers\DuckBugProvider::create('__PUBLIC_DSN__')
    )
]);

(new \DuckBug\Integrations\ErrorHandlerIntegration($duck->getClient()))->register();
```

### Monolog

```php
$logger = new \Monolog\Logger('app');
$logger->pushHandler(new \DuckBug\Monolog\DuckBugHandler());
```

### Symfony

Install `duckbug/duckbug-symfony` (or use the full `duckbug/duckbug` package) and register `DuckBug\\Integrations\\Symfony\\DuckBugExceptionListener` as a listener for `kernel.exception`.

### Laravel

Install `duckbug/duckbug-laravel` (or use the full `duckbug/duckbug` package) and register `DuckBug\\Integrations\\Laravel\\DuckBugServiceProvider` after SDK bootstrapping to wire exception/shutdown capture.

### WordPress

Install `duckbug/duckbug-wordpress` if you bootstrap WordPress through Composer, or use the standalone plugin artifact from releases for classic plugin installation.

Configure it via constants, arrays or the `duckbug_wordpress_config` filter:

```php
define('DUCKBUG_WORDPRESS_DSN', '__PUBLIC_DSN__');
define('DUCKBUG_WORDPRESS_ENVIRONMENT', 'production');
define('DUCKBUG_WORDPRESS_SERVICE', 'wordpress');

add_filter('duckbug_wordpress_config', function (array $config): array {
    $config['privacy'] = [
        'cookies' => false,
        'session' => false,
    ];

    return $config;
});

\DuckBug\Integrations\WordPress\WordPressBootstrap::registerHooks();
```

If you install the standalone plugin, just activate `DuckBug for WordPress` and define the same constants in `wp-config.php`.

### Yii2

Install `duckbug/duckbug-yii2` and wire the target / error handler in your application config:

```php
'components' => [
    'log' => [
        'targets' => [
            [
                'class' => \DuckBug\Integrations\Yii2\DuckBugTarget::class,
                'levels' => ['error', 'warning'],
            ],
        ],
    ],
    'errorHandler' => [
        'class' => \DuckBug\Integrations\Yii2\DuckBugWebErrorHandler::class,
    ],
],
```

For console apps, use `DuckBug\\Integrations\\Yii2\\DuckBugConsoleErrorHandler`.

### PSR-15

Wrap your application with `DuckBug\\Integrations\\Psr15\\DuckBugMiddleware` to capture uncaught HTTP exceptions and automatically emit a request transaction with `traceId` / `spanId`.

### Slim 4

Install `duckbug/duckbug-slim4`, add `DuckBug\\Integrations\\Slim4\\DuckBugMiddleware` to the app stack, and decorate the default error handler:

```php
$app->add(new \DuckBug\Integrations\Slim4\DuckBugMiddleware());

$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$defaultErrorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorMiddleware->setDefaultErrorHandler(
    new \DuckBug\Integrations\Slim4\DuckBugErrorHandler($defaultErrorHandler)
);
```

### Drupal 9/10/11

Install `duckbug/duckbug-drupal`, enable the `duckbug` module, and define DuckBug settings in `settings.php`:

```php
$settings['duckbug'] = [
    'dsn' => '__PUBLIC_DSN__',
    'environment' => 'production',
    'service' => 'drupal',
    'privacy' => [
        'session' => false,
    ],
];
```

The module registers a logger bridge and a `kernel.exception` subscriber automatically. It is intended for Composer-based Drupal installs.

### Drupal 7

Install `duckbug/duckbug-drupal7` or unpack the standalone module archive, then enable `duckbug_drupal7` and define settings in `settings.php`:

```php
$conf['duckbug_dsn'] = '__PUBLIC_DSN__';
$conf['duckbug_environment'] = 'production';
$conf['duckbug_service'] = 'drupal7';
```

The legacy module wires DuckBug through `hook_boot`, `hook_init` and `hook_watchdog`, so it captures runtime errors and Drupal watchdog events without requiring Symfony-style services.

## 📄 License

Licensed under the MIT license, see [LICENSE](LICENSE).
