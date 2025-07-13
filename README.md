# Official DuckBug SDK for PHP

Duck is a developer-focused wrapper for all your PHP loggers. It provides a unified, flexible interface to handle logging and error monitoring across different providers, with support for multiple log levels and context enrichment.

## 🚀 Quick Start

### Install

Install the SDK using [Composer](https://getcomposer.org/).

```bash
composer require duckbug/duckbug
```

### Configuration

Initialize the SDK as early as possible in your application

```php
\DuckBug\Duck::wake([
    new \DuckBug\Core\ProviderSetup(
        \DuckBug\Providers\DuckBugProvider::create('__PUBLIC_DSN__'),
        true, // enable catching Throwable
        false // disable Debug level logs
    )
]);
```

### Usage

#### Log exceptions using `quack()`

```php
try {
    throw new \Exception('foo bar');
} catch (\Exception $exception) {
    \DuckBug\Duck::get()->quack($exception);
}
```

#### Log messages with severity levels

```php
\DuckBug\Duck::get()->warning('User not found', ['userId' => 8]);
```

## ⚙️ Custom and Multiple Providers

#### Create your own Provider

```php
use DuckBug\Core\Provider;
use Psr\Log\LoggerTrait;
use Throwable;

class MyCustomProvider implements Provider
{
    use LoggerTrait;

    public function quack(Throwable $exception, array $context = []): void
    {
        // Your custom logic for handling exceptions
    }

    public function log($level, $message, array $context = []): void
    {
        // Required by the LoggerTrait
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
It is recommended to use this class in your custom implementations of the `Provider` interface to enrich logs with useful request metadata.

#### Example with custom Provider

```php
use DuckBug\Core\Provider;
use DuckBug\Util\Pond;
use Psr\Log\LoggerTrait;
use Throwable;

class MyCustomProvider implements Provider
{
    use LoggerTrait;

    /** @var Pond */
    private $context;

    public function __construct()
    {
        $this->context = Pond::ripple(['password', 'token']);
    }

    public function quack(Throwable $exception, array $context = []): void
    {
        $context['ip'] = $this->context->getUserIp();
        $context['url'] = $this->context->getUrl();
        $context['method'] = $this->context->getMethod();

        // Send enriched context to your storage/logs/etc.
        error_log('[MyCustomProvider] ' . $exception->getMessage() . ' ' . json_encode($context));
    }

    public function log($level, $message, array $context = []): void
    {
        // Optional: implement log-level handling
    }
}
```

## 📄 License

Licensed under the MIT license, see [LICENSE](LICENSE).
