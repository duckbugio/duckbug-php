<?php

declare(strict_types=1);

namespace DuckBug\Providers;

use DuckBug\Core\Provider;
use DuckBug\Duck;
use DuckBug\HttpClient\HttpClient;
use DuckBug\HttpClient\HttpClientInterface;
use DuckBug\Pond;
use Psr\Log\LoggerTrait;
use Throwable;

final class DuckBugProvider implements Provider
{
    use LoggerTrait;

    /** @var Pond */
    private $pond;
    /** @var string */
    private $dsn;
    /** @var bool */
    private $enableEnvLogging;
    /** @var bool */
    private $enableRequestContextLogging;
    /** @var HttpClientInterface */
    private $client;

    private function __construct(
        HttpClientInterface $client,
        string $dsn,
        bool $enableEnvLogging,
        bool $enableRequestContextLogging
    ) {
        $this->dsn = $dsn;
        $this->enableEnvLogging = $enableEnvLogging;
        $this->enableRequestContextLogging = $enableRequestContextLogging;
        $this->pond = Duck::getPond();
        $this->client = $client;
    }

    public static function create(
        string $dsn,
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
                'ip'            => $this->pond->getUserIp(),
                'url'           => $this->pond->getUrl(),
                'method'        => $this->pond->getMethod(),
                'headers'       => $this->pond->getHeaders(),
                'queryParams'   => $this->pond->getQueryParams(),
                'bodyParams'    => $this->pond->getBodyParams(),
                'cookies'       => $this->pond->getCookies(),
                'session'       => $this->pond->getSession(),
                'files'         => $this->pond->getFiles(),
            ];
        }

        if ($this->enableEnvLogging) {
            $data['env'] = $this->pond->getEnv();
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
            'ip'                    => $this->pond->getUserIp(),
            'url'                   => $this->pond->getUrl(),
            'method'                => $this->pond->getMethod(),
            'headers'               => $this->pond->getHeaders(),
            'queryParams'           => $this->pond->getQueryParams(),
            'bodyParams'            => $this->pond->getBodyParams(),
            'cookies'               => $this->pond->getCookies(),
            'session'               => $this->pond->getSession(),
            'files'                 => $this->pond->getFiles(),
        ];

        if ($this->enableEnvLogging) {
            $data['env'] = $this->pond->getEnv();
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
            'CRITICAL'      => 'FATAL',
            'ALERT'         => 'FATAL',
            'EMERGENCY'     => 'FATAL',
        ];

        return $levelMapping[$level] ?? 'INFO';
    }
}
