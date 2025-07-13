<?php

declare(strict_types=1);

namespace DuckBug\Providers;

use DuckBug\Core\Provider;
use DuckBug\HttpClient\HttpClient;
use DuckBug\HttpClient\HttpClientInterface;
use DuckBug\Util\Pond;
use Psr\Log\LoggerTrait;
use Throwable;

final class DuckBugProvider implements Provider
{
    use LoggerTrait;

    /** @var Pond */
    private $requestContext;
    /** @var string */
    private $dsn;
    /** @var bool */
    private $enableEnvLogging;
    /** @var bool */
    private $enableRequestContextLogging;
    /** @var HttpClientInterface */
    private $client;

    /** @param string[] $sensitiveFields */
    private function __construct(
        HttpClientInterface $client,
        string $dsn,
        array $sensitiveFields,
        bool $enableEnvLogging,
        bool $enableRequestContextLogging
    ) {
        $this->dsn = $dsn;
        $this->enableEnvLogging = $enableEnvLogging;
        $this->enableRequestContextLogging = $enableRequestContextLogging;
        $this->requestContext = Pond::ripple($sensitiveFields);
        $this->client = $client;
    }

    /** @param string[] $sensitiveFields */
    public static function create(
        string $dsn,
        array $sensitiveFields = ['password', 'token', 'api_key'],
        bool $enableEnvLogging = false,
        bool $enableRequestContextLogging = true,
        int $timeout = 5,
        int $connectionTimeout = 3
    ): self {
        return new self(
            new HttpClient(
                $timeout,
                $connectionTimeout
            ),
            $dsn,
            $sensitiveFields,
            $enableEnvLogging,
            $enableRequestContextLogging
        );
    }

    /**
     * @param mixed $level
     * @param string $message
     * @psalm-suppress MixedOperand
     */
    public function log($level, $message, array $context = []): void
    {
        $data = [
            'time'          => $this->getMicroTime(),
            'level'         => $this->getLevel((string)$level),
            'message'       => $message,
            'context'       => !empty($context) ? $context : [],
        ];

        if ($this->enableRequestContextLogging) {
            $data += [
                'ip'            => $this->requestContext->getUserIp(),
                'url'           => $this->requestContext->getUrl(),
                'method'        => $this->requestContext->getMethod(),
                'headers'       => $this->requestContext->getHeaders(),
                'queryParams'   => $this->requestContext->getQueryParams(),
                'bodyParams'    => $this->requestContext->getBodyParams(),
                'cookies'       => $this->requestContext->getCookies(),
                'session'       => $this->requestContext->getSession(),
                'files'         => $this->requestContext->getFiles(),
            ];
        }

        if ($this->enableEnvLogging) {
            $data['env'] = $this->requestContext->getEnv();
        }

        $this->client->send($this->dsn, 'logs', $data);
    }

    public function quack(Throwable $exception, array $context = []): void
    {
        $data = [
            'time'                  => $this->getMicroTime(),
            'file'                  => $exception->getFile(),
            'line'                  => $exception->getLine(),
            'message'               => $exception->getMessage(),
            'stacktrace'            => $exception->getTrace(),
            'stacktraceAsString'    => $exception->getTraceAsString(),
            'context'               => !empty($context) ? $context : [],
            'ip'                    => $this->requestContext->getUserIp(),
            'url'                   => $this->requestContext->getUrl(),
            'method'                => $this->requestContext->getMethod(),
            'headers'               => $this->requestContext->getHeaders(),
            'queryParams'           => $this->requestContext->getQueryParams(),
            'bodyParams'            => $this->requestContext->getBodyParams(),
            'cookies'               => $this->requestContext->getCookies(),
            'session'               => $this->requestContext->getSession(),
            'files'                 => $this->requestContext->getFiles(),
        ];

        if ($this->enableEnvLogging) {
            $data['env'] = $this->requestContext->getEnv();
        }

        $this->client->send($this->dsn, 'errors', $data);
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
            'CRITICAL'      => 'ERROR',
            'ALERT'         => 'ERROR',
            'EMERGENCY'     => 'ERROR',
        ];

        return $levelMapping[$level] ?? 'INFO';
    }
}
