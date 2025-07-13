<?php

declare(strict_types=1);

namespace DuckBug\HttpClient;

final class HttpClient implements HttpClientInterface
{
    /** @var int */
    private $timeout;

    /** @var int */
    private $connectionTimeout;

    public function __construct(int $timeout = 5, int $connectionTimeout = 3)
    {
        $this->timeout = $timeout;
        $this->connectionTimeout = $connectionTimeout;
    }

    public function send(string $dsn, string $type, array $data): int
    {
        $ch = curl_init($dsn . '/' . $type);

        curl_setopt_array($ch, [
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS      => json_encode($data),
            CURLOPT_TIMEOUT         => $this->timeout,
            CURLOPT_CONNECTTIMEOUT  => $this->connectionTimeout,
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode;
    }
}
