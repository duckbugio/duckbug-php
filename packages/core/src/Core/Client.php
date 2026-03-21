<?php

declare(strict_types=1);

namespace DuckBug\Core;

use DuckBug\Pond;
use ErrorException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Throwable;

final class Client implements LoggerInterface
{
    use LoggerTrait;

    /** @var ProviderSetup[] */
    private $setups;

    /** @var Scope */
    private $scope;

    /** @var Pond */
    private $pond;

    /**
     * @param ProviderSetup[] $setups
     */
    public function __construct(array $setups = [], ?Scope $scope = null, ?Pond $pond = null)
    {
        $this->setups = $setups;
        $this->scope = $scope ?: new Scope();
        $this->pond = $pond ?: Pond::ripple();
        $this->scope->setSDK(['name' => 'duckbug-php']);
        $this->scope->setRuntime($this->defaultRuntimeContext());
    }

    public function getScope(): Scope
    {
        return $this->scope;
    }

    public function getPond(): Pond
    {
        return $this->pond;
    }

    /**
     * @param mixed $value
     */
    public function setTag(string $key, $value): self
    {
        $this->scope->setTag($key, $value);

        return $this;
    }

    /** @param array<string, scalar|null> $tags */
    public function setTags(array $tags): self
    {
        $this->scope->setTags($tags);

        return $this;
    }

    /** @param array<string, mixed>|null $user */
    public function setUser(?array $user): self
    {
        $this->scope->setUser($user);

        return $this;
    }

    public function setRelease(?string $release): self
    {
        $this->scope->setRelease($release);

        return $this;
    }

    public function setEnvironment(?string $environment): self
    {
        $this->scope->setEnvironment($environment);

        return $this;
    }

    public function setDist(?string $dist): self
    {
        $this->scope->setDist($dist);

        return $this;
    }

    public function setServerName(?string $serverName): self
    {
        $this->scope->setServerName($serverName);

        return $this;
    }

    public function setService(?string $service): self
    {
        $this->scope->setService($service);

        return $this;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->scope->setRequestId($requestId);

        return $this;
    }

    public function setTransaction(?string $transaction): self
    {
        $this->scope->setTransaction($transaction);

        return $this;
    }

    public function setTrace(?string $traceId, ?string $spanId = null): self
    {
        $this->scope->setTrace($traceId, $spanId);

        return $this;
    }

    public function setFingerprint(?string $fingerprint): self
    {
        $this->scope->setFingerprint($fingerprint);

        return $this;
    }

    public function setPlatform(string $platform): self
    {
        $this->scope->setPlatform($platform);

        return $this;
    }

    /** @param array<string, mixed> $extra */
    public function setExtra(array $extra): self
    {
        $this->scope->setExtra($extra);

        return $this;
    }

    /** @param array<string, mixed> $sdk */
    public function setSDK(array $sdk): self
    {
        $this->scope->setSDK(array_merge(['name' => 'duckbug-php'], $sdk));

        return $this;
    }

    /** @param array<string, mixed> $runtime */
    public function setRuntime(array $runtime): self
    {
        $this->scope->setRuntime(array_merge($this->defaultRuntimeContext(), $runtime));

        return $this;
    }

    /** @param array<string, mixed> $breadcrumb */
    public function addBreadcrumb(array $breadcrumb): self
    {
        $this->scope->addBreadcrumb($breadcrumb);

        return $this;
    }

    public function clearBreadcrumbs(): self
    {
        $this->scope->clearBreadcrumbs();

        return $this;
    }

    public function clearUser(): self
    {
        $this->scope->clearUser();

        return $this;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function quack(Throwable $exception, array $context = []): void
    {
        $this->captureException($exception, $context, true, 'manual');
    }

    /**
     * @param array<string, mixed> $context
     */
    public function captureException(
        Throwable $exception,
        array $context = [],
        bool $handled = true,
        string $mechanism = 'manual'
    ): void {
        $event = Event::error($this->buildErrorPayload($exception, $context, $handled, $mechanism));

        foreach ($this->setups as $setup) {
            if (!$setup->enabledThrowable) {
                continue;
            }
            $setup->provider->captureEvent($event);
        }
    }

    public function captureError(
        int $severity,
        string $message,
        string $file,
        int $line,
        array $context = [],
        bool $handled = false,
        string $mechanism = 'error_handler'
    ): void {
        /** @var array<string, mixed> $context */
        $this->captureException(new ErrorException($message, 0, $severity, $file, $line), $context, $handled, $mechanism);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array<string, mixed> $context
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function log($level, $message, array $context = []): void
    {
        $rawLevel = strtoupper(trim((string)$level));
        $normalizedLevel = $this->normalizeLevel($rawLevel);
        if ($normalizedLevel === null) {
            return;
        }

        $event = Event::log($this->buildLogPayload($normalizedLevel, $message, $context));

        foreach ($this->setups as $setup) {
            if (!$this->isLevelEnabled($setup, $rawLevel)) {
                continue;
            }
            $setup->provider->captureEvent($event);
        }
    }

    public function flush(): void
    {
        foreach ($this->setups as $setup) {
            if ($setup->provider instanceof FlushableProvider) {
                $setup->provider->flush();
            }
        }
    }

    public function startTransaction(string $name, string $op = 'custom'): Transaction
    {
        return new Transaction(
            $name,
            $op,
            $this->generateTraceId(),
            $this->generateSpanId(),
            $this->getMicroTime(),
            function (): string {
                return $this->generateSpanId();
            }
        );
    }

    public function captureTransaction(Transaction $transaction): void
    {
        $event = Event::transaction($this->buildTransactionPayload($transaction));

        foreach ($this->setups as $setup) {
            $setup->provider->captureEvent($event);
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildLogPayload(string $level, string $message, array $context): array
    {
        $payload = $this->buildBasePayload($context);
        $payload['time'] = $this->getMicroTime();
        $payload['level'] = $level;
        $payload['message'] = $message;
        $payload['context'] = $context;

        return $payload;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildErrorPayload(
        Throwable $exception,
        array $context,
        bool $handled,
        string $mechanism
    ): array {
        $payload = $this->buildBasePayload($context);
        $payload['time'] = $this->getMicroTime();
        $payload['message'] = $exception->getMessage();
        $payload['file'] = $exception->getFile();
        $payload['line'] = $exception->getLine();
        $payload['stacktrace'] = $this->extractFrames($exception);
        $payload['stacktraceAsString'] = $this->formatThrowable($exception);
        $payload['exception'] = $this->buildThrowablePayload($exception, $handled, $mechanism);
        $payload['context'] = $context;
        $payload['handled'] = $handled;
        $payload['mechanism'] = $mechanism;

        return $payload;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildBasePayload(array $context): array
    {
        $metadata = $this->scope->toMetadata();
        $requestContext = $this->pond->getContext();

        return [
            'eventId' => $this->generateEventId(),
            'platform' => $metadata['platform'],
            'release' => $metadata['release'],
            'environment' => $metadata['environment'],
            'dist' => $metadata['dist'],
            'serverName' => $metadata['serverName'],
            'service' => $metadata['service'],
            'requestId' => $metadata['requestId'],
            'transaction' => $metadata['transaction'],
            'traceId' => $metadata['traceId'],
            'spanId' => $metadata['spanId'],
            'fingerprint' => $metadata['fingerprint'],
            'dTags' => $this->mergeTags(
                /** @var array<string, string> $dTags */
                $metadata['dTags'],
                $context
            ),
            'sdk' => $metadata['sdk'],
            'runtime' => $metadata['runtime'],
            'breadcrumbs' => $metadata['breadcrumbs'],
            'extra' => $metadata['extra'],
            'user' => $metadata['user'],
            'ip' => $requestContext['ip'],
            'url' => $requestContext['url'],
            'method' => $requestContext['method'],
            'headers' => $requestContext['headers'],
            'queryParams' => $requestContext['queryParams'],
            'bodyParams' => $requestContext['bodyParams'],
            'cookies' => $requestContext['cookies'],
            'session' => $requestContext['session'],
            'files' => $requestContext['files'],
            'env' => $requestContext['env'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTransactionPayload(Transaction $transaction): array
    {
        $payload = $this->buildBasePayload([]);
        $endTimestampMs = $transaction->getEndTimestampMs();
        $spans = [];

        foreach ($transaction->getSpans() as $span) {
            $spans[] = $span->toPayload();
        }

        $payload['traceId'] = $transaction->getTraceId();
        $payload['spanId'] = $transaction->getSpanId();
        $payload['transaction'] = $transaction->getName();
        $payload['op'] = $transaction->getOp();
        $payload['status'] = $transaction->getStatus();
        $payload['context'] = $transaction->getContext();
        $payload['measurements'] = !empty($transaction->getMeasurements()) ? $transaction->getMeasurements() : null;
        $payload['spans'] = !empty($spans) ? $spans : null;
        $payload['startTime'] = $transaction->getStartTimestampMs();
        $payload['endTime'] = $endTimestampMs !== null ? $endTimestampMs : $transaction->getStartTimestampMs();
        $payload['duration'] = $transaction->getDurationMs();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildThrowablePayload(Throwable $exception, bool $handled, string $mechanism): array
    {
        $payload = [
            'type' => \get_class($exception),
            'value' => $exception->getMessage(),
            'code' => $this->normalizeThrowableCode($exception),
            'handled' => $handled,
            'mechanism' => $mechanism,
            'stacktrace' => $this->extractFrames($exception),
        ];

        $causes = $this->buildThrowableCauses($exception->getPrevious());
        if (!empty($causes)) {
            $payload['causes'] = $causes;
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildThrowableCauses(?Throwable $exception): array
    {
        $causes = [];

        while ($exception !== null) {
            $cause = [
                'type' => \get_class($exception),
                'value' => $exception->getMessage(),
                'code' => $this->normalizeThrowableCode($exception),
                'stacktrace' => $this->extractFrames($exception),
            ];

            $causes[] = $cause;
            $exception = $exception->getPrevious();
        }

        return $causes;
    }

    /**
     * @return int|string
     */
    private function normalizeThrowableCode(Throwable $exception)
    {
        return $exception->getCode();
    }

    private function getMicroTime(): int
    {
        return (int)round(microtime(true) * 1000);
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

    private function formatThrowable(Throwable $exception): string
    {
        return \get_class($exception) . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString();
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

    /**
     * @param string[] $scopeTags
     * @param array<string, mixed> $context
     * @return string[]
     */
    private function mergeTags(array $scopeTags, array $context): array
    {
        $tags = $scopeTags;

        foreach (['dTags', 'tags'] as $field) {
            if (!isset($context[$field]) || !\is_array($context[$field])) {
                continue;
            }

            /** @var mixed $value */
            foreach ($context[$field] as $key => $value) {
                if (!is_scalar($value) && $value !== null) {
                    continue;
                }

                $tag = \is_string($key)
                    ? (
                        $value === null
                            ? trim($key)
                            : trim($key) . ':' . trim((string)$value)
                    )
                    : trim((string)$value);
                if ($tag === '') {
                    continue;
                }

                $tags[] = $tag;
            }
        }

        return array_values(array_unique($tags));
    }

    private function normalizeLevel(string $level): ?string
    {
        $level = strtoupper(trim($level));

        switch ($level) {
            case 'DEBUG':
                return 'DEBUG';
            case 'INFO':
            case 'NOTICE':
                return 'INFO';
            case 'WARNING':
                return 'WARN';
            case 'ERROR':
                return 'ERROR';
            case 'CRITICAL':
            case 'ALERT':
            case 'EMERGENCY':
                return 'FATAL';
            default:
                return null;
        }
    }

    private function isLevelEnabled(ProviderSetup $setup, string $rawLevel): bool
    {
        $rawLevel = strtoupper(trim($rawLevel));

        switch ($rawLevel) {
            case 'DEBUG':
                return $setup->enabledDebug;
            case 'INFO':
                return $setup->enabledInfo;
            case 'NOTICE':
                return $setup->enabledNotice;
            case 'WARNING':
                return $setup->enabledWarning;
            case 'ERROR':
                return $setup->enabledError;
            case 'CRITICAL':
                return $setup->enabledCritical;
            case 'ALERT':
                return $setup->enabledAlert;
            case 'EMERGENCY':
                return $setup->enabledEmergency;
            default:
                return false;
        }
    }

    private function generateEventId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
