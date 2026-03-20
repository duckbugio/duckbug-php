<?php

declare(strict_types=1);

namespace DuckBug\Providers;

use DuckBug\Core\Event;
use DuckBug\Core\EventAwareProvider;
use DuckBug\Core\FlushableProvider;
use DuckBug\Core\Provider;
use DuckBug\HttpClient\HttpClient;
use DuckBug\HttpClient\HttpClientInterface;
use DuckBug\Pond;
use Psr\Log\LoggerTrait;
use Throwable;

final class DuckBugProvider implements Provider, EventAwareProvider, FlushableProvider
{
    use LoggerTrait;

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

    /**
     * @param mixed $level
     * @param string $message
     * @param array<string, mixed> $context
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function log($level, $message, array $context = []): void
    {
        $this->captureEvent(Event::log($this->prepareFallbackLogPayload((string)$level, $message, $context)));
    }

    /**
     * @param array<string, mixed> $context
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function quack(Throwable $exception, array $context = []): void
    {
        $this->captureEvent(Event::error($this->prepareFallbackErrorPayload($exception, $context)));
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
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function prepareFallbackLogPayload(string $level, string $message, array $context): array
    {
        $metadata = $this->extractMetadataFromContext($context);
        $data = [
            'eventId' => $this->generateEventId(),
            'time'          => $this->getMicroTime(),
            'level'         => $this->getLevel($level),
            'message'       => $message,
            'context'       => !empty($context) ? $context : [],
            'platform'      => $metadata['platform'],
            'release'       => $metadata['release'],
            'environment'   => $metadata['environment'],
            'dist'          => $metadata['dist'],
            'serverName'    => $metadata['serverName'],
            'service'       => $metadata['service'],
            'requestId'     => $metadata['requestId'],
            'transaction'   => $metadata['transaction'],
            'traceId'       => $metadata['traceId'],
            'spanId'        => $metadata['spanId'],
            'fingerprint'   => $metadata['fingerprint'],
            'dTags'         => $metadata['dTags'],
            'sdk'           => $metadata['sdk'],
            'runtime'       => $metadata['runtime'],
            'breadcrumbs'   => $metadata['breadcrumbs'],
            'extra'         => $metadata['extra'],
            'user'          => $metadata['user'],
        ];

        return array_merge($data, $this->getFallbackRequestContext());
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function prepareFallbackErrorPayload(Throwable $exception, array $context): array
    {
        $metadata = $this->extractMetadataFromContext($context);
        $data = [
            'eventId' => $this->generateEventId(),
            'time'                  => $this->getMicroTime(),
            'file'                  => $exception->getFile(),
            'line'                  => $exception->getLine(),
            'message'               => $exception->getMessage(),
            'stacktrace'            => $this->extractFrames($exception),
            'stacktraceAsString'    => \get_class($exception) . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString(),
            'exception'             => $this->buildThrowablePayload($exception),
            'context'               => !empty($context) ? $context : [],
            'platform'              => $metadata['platform'],
            'release'               => $metadata['release'],
            'environment'           => $metadata['environment'],
            'dist'                  => $metadata['dist'],
            'serverName'            => $metadata['serverName'],
            'service'               => $metadata['service'],
            'requestId'             => $metadata['requestId'],
            'transaction'           => $metadata['transaction'],
            'traceId'               => $metadata['traceId'],
            'spanId'                => $metadata['spanId'],
            'fingerprint'           => $metadata['fingerprint'],
            'dTags'                 => $metadata['dTags'],
            'sdk'                   => $metadata['sdk'],
            'runtime'               => $metadata['runtime'],
            'breadcrumbs'           => $metadata['breadcrumbs'],
            'extra'                 => $metadata['extra'],
            'user'                  => $metadata['user'],
            'handled'               => true,
            'mechanism'             => 'manual',
        ];

        return array_merge($data, $this->getFallbackRequestContext());
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

    /** @return array<string, mixed> */
    private function getFallbackRequestContext(): array
    {
        return Pond::ripple(['password', 'token', 'api_key', 'authorization', 'cookie', 'session'])
            ->getContext();
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
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function extractMetadataFromContext(array $context): array
    {
        $sdk = isset($context['sdk']) && \is_array($context['sdk'])
            ? array_merge(['name' => 'duckbug-php'], $context['sdk'])
            : ['name' => 'duckbug-php'];
        $runtime = isset($context['runtime']) && \is_array($context['runtime'])
            ? array_merge($this->defaultRuntimeContext(), $context['runtime'])
            : $this->defaultRuntimeContext();

        return [
            'platform' => isset($context['platform']) && \is_string($context['platform'])
                ? trim($context['platform']) ?: 'php'
                : 'php',
            'release' => $this->extractStringContextValue($context, 'release'),
            'environment' => $this->extractStringContextValue($context, 'environment'),
            'dist' => $this->extractStringContextValue($context, 'dist'),
            'serverName' => $this->extractStringContextValue($context, 'serverName'),
            'service' => $this->extractStringContextValue($context, 'service'),
            'requestId' => $this->extractStringContextValue($context, 'requestId'),
            'transaction' => $this->extractStringContextValue($context, 'transaction'),
            'traceId' => $this->extractStringContextValue($context, 'traceId'),
            'spanId' => $this->extractStringContextValue($context, 'spanId'),
            'fingerprint' => $this->extractStringContextValue($context, 'fingerprint'),
            'dTags' => $this->extractTagsFromContext($context),
            'sdk' => $sdk,
            'runtime' => $runtime,
            'breadcrumbs' => $context['breadcrumbs'] ?? null,
            'extra' => $context['extra'] ?? null,
            'user' => $context['user'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return string[]
     */
    private function extractTagsFromContext(array $context): array
    {
        $tags = [];

        foreach (['dTags', 'tags'] as $field) {
            if (!isset($context[$field]) || !\is_array($context[$field])) {
                continue;
            }

            foreach ($this->filterTagValues($context[$field]) as $key => $value) {
                $tag = \is_string($key)
                    ? ($value === null ? trim($key) : trim($key) . ':' . trim((string)$value))
                    : trim((string)$value);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractStringContextValue(array $context, string $key): ?string
    {
        if (!isset($context[$key]) || !is_scalar($context[$key])) {
            return null;
        }

        $value = trim((string)$context[$key]);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<array-key, mixed> $rawTags
     * @return array<array-key, scalar|null>
     */
    private function filterTagValues(array $rawTags): array
    {
        $filtered = [];

        foreach ($rawTags as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildThrowablePayload(Throwable $exception): array
    {
        $payload = [
            'type' => \get_class($exception),
            'value' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'handled' => true,
            'mechanism' => 'manual',
            'stacktrace' => $this->extractFrames($exception),
        ];

        $causes = [];
        $previous = $exception->getPrevious();
        while ($previous !== null) {
            $causes[] = [
                'type' => \get_class($previous),
                'value' => $previous->getMessage(),
                'code' => $previous->getCode(),
                'stacktrace' => $this->extractFrames($previous),
            ];
            $previous = $previous->getPrevious();
        }

        if (!empty($causes)) {
            $payload['causes'] = $causes;
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

    private function getMicroTime(): int
    {
        return (int)round(microtime(true) * 1000);
    }

    private function getLevel(string $level): string
    {
        $level = strtoupper(trim($level));

        $levelMapping = [
            'DEBUG'         => 'DEBUG',
            'INFO'          => 'INFO',
            'NOTICE'        => 'INFO',
            'WARNING'       => 'WARN',
            'ERROR'         => 'ERROR',
            'CRITICAL'      => 'FATAL',
            'ALERT'         => 'FATAL',
            'EMERGENCY'     => 'FATAL',
        ];

        return $levelMapping[$level] ?? 'INFO';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractFrames(Throwable $exception): array
    {
        $frames = [[
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => null,
            'class' => \get_class($exception),
            'type' => null,
        ]];

        foreach ($exception->getTrace() as $frame) {
            $frames[] = [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ];
        }

        return $frames;
    }

    private function generateEventId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** @return array<string, string> */
    private function defaultRuntimeContext(): array
    {
        return [
            'language' => 'php',
            'version' => PHP_VERSION,
            'sapi' => \PHP_SAPI,
            'os' => PHP_OS,
        ];
    }
}
