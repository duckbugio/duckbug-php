<?php

declare(strict_types=1);

namespace DuckBug\HttpClient;

final class HttpClient implements HttpClientInterface
{
    private const STATUS_REQUEST_TIMEOUT = 408;

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
     * differently. This predicate is the shared one: duckbug-go and duckbug-js
     * answer exactly the same question the same way, and a change here has to
     * land in all three.
     *
     * The rule is "transient unless proven final": a request that never
     * produced a response, 408, 429 and every 5xx are worth another attempt;
     * 501 is the single carve-out; everything else is the final answer.
     *
     * It is written as a rule with one hole rather than as a list of retriable
     * codes on purpose. This client does not only talk to DuckBug's ingest - a
     * DuckBug installation sits behind whatever edge the customer runs, and
     * that edge invents statuses of its own. An allow list turns every code it
     * has not been taught about into a silently dropped event, which is the one
     * failure an error tracker must not have, and widening it means shipping a
     * new SDK into every consumer's dependency tree. Being wrong the other way
     * costs at most $maxRetries extra requests with bounded backoff, and a
     * retry of a request that did arrive cannot create a second event: ingest
     * deduplicates on the event id with a Postgres primary key and ON CONFLICT
     * DO NOTHING, with no expiry, on the single and the batch route alike.
     *
     * That guarantee is only as strong as the id. It has to be supplied by the
     * caller: when a payload reaches ingest without an "eventId", the server
     * mints a fresh one per request and a retry does store the event twice.
     * Client::generateEventId() sets a UUIDv4 on every event it builds, so the
     * normal path is safe. The server validates it as uuid4, so a non-UUID
     * idempotency key is rejected with 400 rather than honoured.
     *
     * 408 is retried because it is the edge timing out the request body
     * (nginx client_body_timeout and friends), never a verdict on the payload;
     * RFC 9110 states outright that such a request may be repeated unchanged.
     *
     * 501 is the hole: it is DuckBug stating that the capability is not
     * configured in this installation, and only an operator can change that.
     * The backend reaches for 501 over 503 in exactly that case so clients stop
     * retrying, because 503 would promise that waiting helps. A 501 from an
     * intermediary means the same thing one layer out, so the answer is the
     * same either way.
     *
     * Do not widen this carve-out to 503. On the ingest path a 503 is the edge
     * during a redeploy - the transient case this predicate exists for.
     *
     * Deliberate difference from the other two SDKs: a request that never
     * reached a response arrives here as a non-null error message, because that
     * is how cURL reports a dial, TLS or timeout failure - it sets an errno and
     * leaves the status at 0. duckbug-go sees the same case as an error from
     * http.Client.Do and duckbug-js as a rejected fetch promise; all three
     * retry it.
     *
     * The 429 that DuckBug's rate limiter returns carries Retry-After, which
     * this client does not read - the backoff in request() decides on its own.
     * Honouring it is a separate change and has to land in all three SDKs
     * together.
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

        if ($statusCode === self::STATUS_REQUEST_TIMEOUT || $statusCode === self::STATUS_TOO_MANY_REQUESTS) {
            return true;
        }

        return $statusCode >= self::STATUS_SERVER_ERROR_MIN;
    }
}
