<?php

declare(strict_types=1);

namespace DuckBug\Providers;

use DuckBug\Core\Event;
use DuckBug\Core\FlushableProvider;
use DuckBug\Core\Provider;
use DuckBug\HttpClient\HttpClient;
use DuckBug\HttpClient\HttpClientInterface;

final class DuckBugProvider implements Provider, FlushableProvider
{
    /** @var string */
    private $dsn;
    /** @var bool */
    private $enableEnvLogging;
    /** @var bool */
    private $enableRequestContextLogging;
    /** @var HttpClientInterface */
    private $client;
    /** @var int */
    private $batchSize;
    /** @var bool */
    private $captureHeaders = true;
    /** @var bool */
    private $captureBody = true;
    /** @var bool */
    private $captureSession = true;
    /** @var bool */
    private $captureCookies = true;
    /** @var bool */
    private $captureFiles = true;
    /**
     * @var callable|null
     * @psalm-var callable(array<string, mixed>): (array<string, mixed>|null)|null
     */
    private $beforeSend;
    /** @var callable|null */
    private $transportFailureHandler;
    /** @var array<string, array<int, array<string, mixed>>> */
    private $buffers = [
        Event::TYPE_LOG => [],
        Event::TYPE_ERROR => [],
    ];

    private function __construct(
        HttpClientInterface $client,
        string $dsn,
        bool $enableEnvLogging,
        bool $enableRequestContextLogging,
        int $batchSize
    ) {
        $this->dsn = $dsn;
        $this->enableEnvLogging = $enableEnvLogging;
        $this->enableRequestContextLogging = $enableRequestContextLogging;
        $this->client = $client;
        $this->batchSize = $batchSize > 1 ? $batchSize : 1;
        register_shutdown_function([$this, 'flush']);
    }

    public static function create(
        string $dsn,
        bool $enableEnvLogging = false,
        bool $enableRequestContextLogging = true,
        int $timeout = 5,
        int $connectionTimeout = 3,
        int $batchSize = 1,
        int $maxRetries = 2,
        int $retryDelayMs = 100
    ): self {
        return new self(
            new HttpClient(
                $timeout,
                $connectionTimeout,
                $maxRetries,
                $retryDelayMs
            ),
            $dsn,
            $enableEnvLogging,
            $enableRequestContextLogging,
            $batchSize
        );
    }

    /**
     * @param callable(array<string, mixed>): (array<string, mixed>|null) $beforeSend
     */
    public function setBeforeSend(callable $beforeSend): self
    {
        $this->beforeSend = $beforeSend;

        return $this;
    }

    public function setTransportFailureHandler(callable $handler): self
    {
        $this->transportFailureHandler = $handler;

        return $this;
    }

    /** @param array<string, bool> $options */
    public function configurePrivacy(array $options): self
    {
        if (isset($options['headers'])) {
            $this->captureHeaders = $options['headers'];
        }

        if (isset($options['body'])) {
            $this->captureBody = $options['body'];
        }

        if (isset($options['session'])) {
            $this->captureSession = $options['session'];
        }

        if (isset($options['cookies'])) {
            $this->captureCookies = $options['cookies'];
        }

        if (isset($options['files'])) {
            $this->captureFiles = $options['files'];
        }

        if (isset($options['env'])) {
            $this->enableEnvLogging = $options['env'];
        }

        if (isset($options['request'])) {
            $this->enableRequestContextLogging = $options['request'];
        }

        return $this;
    }

    public function captureEvent(Event $event): void
    {
        $type = $event->getType();
        $payload = $this->preparePayload($event->getPayload());
        if ($payload === null) {
            return;
        }

        if ($type === Event::TYPE_TRANSACTION) {
            $result = $this->client->send($this->dsn, $type, $payload);
            $this->handleTransportResult($type, [$payload], $result);
            return;
        }

        if ($this->batchSize === 1) {
            $result = $this->client->send($this->dsn, $type, $payload);
            $this->handleTransportResult($type, [$payload], $result);
            return;
        }

        $this->buffers[$type][] = $payload;
        if (\count($this->buffers[$type]) >= $this->batchSize) {
            $this->flushType($type);
        }
    }

    public function flush(): void
    {
        $this->flushType(Event::TYPE_LOG);
        $this->flushType(Event::TYPE_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function preparePayload(array $payload): ?array
    {
        if (!$this->enableRequestContextLogging) {
            unset(
                $payload['ip'],
                $payload['url'],
                $payload['method'],
                $payload['headers'],
                $payload['queryParams'],
                $payload['bodyParams'],
                $payload['cookies'],
                $payload['session'],
                $payload['files']
            );
        } else {
            if (!$this->captureHeaders) {
                unset($payload['headers']);
            }

            if (!$this->captureBody) {
                unset($payload['bodyParams']);
            }

            if (!$this->captureSession) {
                unset($payload['session']);
            }

            if (!$this->captureCookies) {
                unset($payload['cookies']);
            }

            if (!$this->captureFiles) {
                unset($payload['files']);
            }
        }

        if (!$this->enableEnvLogging) {
            unset($payload['env']);
        }

        $payload = $this->stripNullValues($payload);
        if ($this->beforeSend !== null) {
            $beforeSend = $this->beforeSend;
            $processed = $beforeSend($payload);
            if ($processed === null) {
                return null;
            }

            $payload = $this->stripNullValues($processed);
        }

        return $payload;
    }

    private function flushType(string $type): void
    {
        if (empty($this->buffers[$type])) {
            return;
        }

        $items = $this->buffers[$type];
        $this->buffers[$type] = [];

        if (\count($items) === 1) {
            $result = $this->client->send($this->dsn, $type, $items[0]);
            $this->handleTransportResult($type, $items, $result);
            return;
        }

        $result = $this->client->sendBatch($this->dsn, $type, $items);
        $this->handleTransportResult($type, $items, $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function stripNullValues(array $payload): array
    {
        /** @var mixed $value */
        foreach ($payload as $key => $value) {
            if ($value === null) {
                unset($payload[$key]);
                continue;
            }

            if (\is_array($value)) {
                /** @var array<string, mixed> $value */
                $payload[$key] = $this->stripNullValues($value);
            }
        }

        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function handleTransportResult(string $type, array $items, \DuckBug\HttpClient\TransportResult $result): void
    {
        if ($result->isSuccess()) {
            return;
        }

        $message = sprintf(
            '[DuckBug] transport failed for %s (%d item(s), status=%d, attempts=%d, error=%s)',
            $type,
            \count($items),
            $result->getStatusCode(),
            $result->getAttempts(),
            $result->getErrorMessage() !== null ? $result->getErrorMessage() : 'unknown'
        );

        if ($this->transportFailureHandler !== null) {
            \call_user_func($this->transportFailureHandler, $type, $items, $result, $message);
            return;
        }

        error_log($message);
    }
}
