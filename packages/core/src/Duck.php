<?php

declare(strict_types=1);

namespace DuckBug;

use DuckBug\Core\Client;
use DuckBug\Core\ProviderSetup;
use DuckBug\Core\Scope;
use DuckBug\Core\Transaction;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Throwable;

final class Duck implements LoggerInterface
{
    use LoggerTrait;

    /** @var Duck|null */
    private static $duck;
    /** @var Pond|null */
    private static $pond;
    /** @var Client */
    private $client;

    /**
     * @param ProviderSetup[] $setups
     * @param string[] $sensitiveFields
     */
    private function __construct(
        array $setups = [],
        array $sensitiveFields = []
    ) {
        self::$pond = Pond::ripple($sensitiveFields);
        $this->client = new Client($setups, null, self::$pond);
    }

    /**
     * @param ProviderSetup[] $setups
     * @param string[] $sensitiveFields
     */
    public static function wake(
        array $setups = [],
        array $sensitiveFields = ['password', 'token', 'api_key', 'authorization', 'cookie', 'session', 'secret']
    ): self {
        self::$duck = new self($setups, $sensitiveFields);

        return self::$duck;
    }

    /**
     * @throws Exception
     */
    public static function get(): self
    {
        if (self::$duck === null) {
            throw new Exception('Duck::get() was called before Duck::wake()');
        }

        return self::$duck;
    }

    public static function getPond(): Pond
    {
        if (self::$pond === null) {
            self::$pond = Pond::ripple();
        }

        return self::$pond;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function quack(Throwable $exception, array $context = []): void
    {
        $this->client->quack($exception, $context);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array<string, mixed> $context
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function log($level, $message, array $context = []): void
    {
        $this->client->log($level, $message, $context);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getScope(): Scope
    {
        return $this->client->getScope();
    }

    /**
     * @param mixed $value
     */
    public function setTag(string $key, $value): self
    {
        $this->client->setTag($key, $value);

        return $this;
    }

    /** @param array<string, scalar|null> $tags */
    public function setTags(array $tags): self
    {
        $this->client->setTags($tags);

        return $this;
    }

    /** @param array<string, mixed>|null $user */
    public function setUser(?array $user): self
    {
        $this->client->setUser($user);

        return $this;
    }

    public function clearUser(): self
    {
        $this->client->clearUser();

        return $this;
    }

    public function setRelease(?string $release): self
    {
        $this->client->setRelease($release);

        return $this;
    }

    public function setEnvironment(?string $environment): self
    {
        $this->client->setEnvironment($environment);

        return $this;
    }

    public function setDist(?string $dist): self
    {
        $this->client->setDist($dist);

        return $this;
    }

    public function setServerName(?string $serverName): self
    {
        $this->client->setServerName($serverName);

        return $this;
    }

    public function setService(?string $service): self
    {
        $this->client->setService($service);

        return $this;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->client->setRequestId($requestId);

        return $this;
    }

    public function setTransaction(?string $transaction): self
    {
        $this->client->setTransaction($transaction);

        return $this;
    }

    public function setTrace(?string $traceId, ?string $spanId = null): self
    {
        $this->client->setTrace($traceId, $spanId);

        return $this;
    }

    public function setFingerprint(?string $fingerprint): self
    {
        $this->client->setFingerprint($fingerprint);

        return $this;
    }

    public function setPlatform(string $platform): self
    {
        $this->client->setPlatform($platform);

        return $this;
    }

    /** @param array<string, mixed> $sdk */
    public function setSDK(array $sdk): self
    {
        $this->client->setSDK($sdk);

        return $this;
    }

    /** @param array<string, mixed> $runtime */
    public function setRuntime(array $runtime): self
    {
        $this->client->setRuntime($runtime);

        return $this;
    }

    /** @param array<string, mixed> $extra */
    public function setExtra(array $extra): self
    {
        $this->client->setExtra($extra);

        return $this;
    }

    /** @param array<string, mixed> $breadcrumb */
    public function addBreadcrumb(array $breadcrumb): self
    {
        $this->client->addBreadcrumb($breadcrumb);

        return $this;
    }

    public function clearBreadcrumbs(): self
    {
        $this->client->clearBreadcrumbs();

        return $this;
    }

    /** @param array<string, mixed> $context */
    public function captureException(
        Throwable $exception,
        array $context = [],
        bool $handled = true,
        string $mechanism = 'manual'
    ): void {
        $this->client->captureException($exception, $context, $handled, $mechanism);
    }

    /** @param array<string, mixed> $context */
    public function captureError(
        int $severity,
        string $message,
        string $file,
        int $line,
        array $context = [],
        bool $handled = false,
        string $mechanism = 'error_handler'
    ): void {
        $this->client->captureError($severity, $message, $file, $line, $context, $handled, $mechanism);
    }

    public function flush(): void
    {
        $this->client->flush();
    }

    public function startTransaction(string $name, string $op = 'custom'): Transaction
    {
        return $this->client->startTransaction($name, $op);
    }

    public function captureTransaction(Transaction $transaction): void
    {
        $this->client->captureTransaction($transaction);
    }
}
