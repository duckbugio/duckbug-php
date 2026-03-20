<?php

declare(strict_types=1);

namespace DuckBug\HttpClient;

final class HttpClient implements HttpClientInterface
{
    /** @var int */
    private $timeout;

    /** @var int */
    private $connectionTimeout;

    /** @var int */
    private $maxRetries;

    /** @var int */
    private $retryDelayMs;

    public function __construct(
        int $timeout = 5,
        int $connectionTimeout = 3,
        int $maxRetries = 2,
        int $retryDelayMs = 100
    ) {
        $this->timeout = $timeout;
        $this->connectionTimeout = $connectionTimeout;
        $this->maxRetries = $maxRetries;
        $this->retryDelayMs = $retryDelayMs;
    }

    public function send(string $dsn, string $type, array $data): TransportResult
    {
        return $this->request($dsn . '/' . $type, $data);
    }

    public function sendBatch(string $dsn, string $type, array $items): TransportResult
    {
        return $this->request($dsn . '/' . $type . '/batch', $items);
    }

    private function request(string $url, array $payload): TransportResult
    {
        $body = json_encode($payload);
        if ($body === false) {
            return new TransportResult(0, '', 'Failed to encode request payload', 1);
        }

        $attempts = 0;
        $maxAttempts = $this->maxRetries + 1;

        do {
            ++$attempts;
            $result = $this->execute($url, $body, $attempts);
            if ($result->isSuccess()) {
                return $result;
            }

            $statusCode = $result->getStatusCode();
            $isRetriable = $result->getErrorMessage() !== null || $statusCode === 429 || $statusCode >= 500;
            if (!$isRetriable || $attempts >= $maxAttempts) {
                return $result;
            }

            $delay = $this->retryDelayMs * (int)2**($attempts - 1) * 1000;
            /** @var positive-int $delay */
            usleep($delay);
        } while ($attempts < $maxAttempts);

        return $result;
    }

    private function execute(string $url, string $body, int $attempts): TransportResult
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS      => $body,
            CURLOPT_TIMEOUT         => $this->timeout,
            CURLOPT_CONNECTTIMEOUT  => $this->connectionTimeout,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            return new TransportResult($httpCode, '', $curlError !== '' ? $curlError : 'Unknown cURL error', $attempts);
        }

        return new TransportResult($httpCode, \is_string($response) ? $response : '', null, $attempts);
    }
}
