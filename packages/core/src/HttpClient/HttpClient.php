<?php

declare(strict_types=1);

namespace DuckBug\HttpClient;

final class HttpClient implements HttpClientInterface
{
    private const STATUS_TOO_MANY_REQUESTS = 429;

    private const STATUS_SERVER_ERROR_MIN = 500;

    private const STATUS_NOT_IMPLEMENTED = 501;

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

            if (!self::isRetriable($result) || $attempts >= $maxAttempts) {
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

    /**
     * Tells whether sending the very same request again can plausibly end
     * differently: transport errors, throttling (429) and server-side faults are
     * expected to clear up on their own, so they are worth another attempt.
     *
     * 501 is the deliberate hole in the 5xx range. It is the server stating that
     * it does not implement the capability at all - DuckBug answers it when a
     * feature is not configured in this installation - and no amount of waiting
     * turns that into a success; an operator has to change the installation
     * first. Repeating it only burns the caller's budget and delays the error
     * they need to see.
     *
     * The exception is written as a single carve-out rather than an allow list
     * of retriable codes on purpose: every other 5xx, including codes that
     * proxies or future server versions invent, keeps its transient-by-default
     * treatment.
     */
    private static function isRetriable(TransportResult $result): bool
    {
        if ($result->getErrorMessage() !== null) {
            return true;
        }

        $statusCode = $result->getStatusCode();

        if ($statusCode === self::STATUS_NOT_IMPLEMENTED) {
            return false;
        }

        return $statusCode === self::STATUS_TOO_MANY_REQUESTS || $statusCode >= self::STATUS_SERVER_ERROR_MIN;
    }
}
