<?php

declare(strict_types=1);

namespace DuckBug\Tests\Unit\HttpClient;

use DuckBug\HttpClient\HttpClient;
use DuckBug\HttpClient\TransportResult;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The delivery loop itself goes through cURL, so the decision that drives it is
 * pinned here: which failures are worth sending again, and which ones are the
 * final answer.
 *
 * @internal
 */
final class HttpClientRetryPolicyTest extends TestCase
{
    public function testTransientServerFailuresAreRetried(): void
    {
        foreach ([500, 502, 503, 504] as $statusCode) {
            self::assertTrue(
                self::isRetriable(new TransportResult($statusCode)),
                sprintf('Status %d is transient and must stay retriable', $statusCode)
            );
        }
    }

    public function testThrottlingIsRetried(): void
    {
        self::assertTrue(
            self::isRetriable(new TransportResult(429)),
            'Status 429 asks for a later attempt, not for giving up'
        );
    }

    public function testNotImplementedIsTerminal(): void
    {
        self::assertFalse(
            self::isRetriable(new TransportResult(501)),
            'Status 501 means the capability is not implemented in this installation, '
            . 'so repeating the request cannot change the answer'
        );
    }

    public function testClientErrorsAreTerminal(): void
    {
        foreach ([400, 401, 403, 404, 413, 422] as $statusCode) {
            self::assertFalse(
                self::isRetriable(new TransportResult($statusCode)),
                sprintf('Status %d is the caller\'s fault and must not be retried', $statusCode)
            );
        }
    }

    public function testTransportErrorsAreRetried(): void
    {
        self::assertTrue(
            self::isRetriable(new TransportResult(0, '', 'Connection timed out')),
            'A request that never reached the server must be retried'
        );
    }

    private static function isRetriable(TransportResult $result): bool
    {
        $method = new ReflectionMethod(HttpClient::class, 'isRetriable');
        $method->setAccessible(true);

        return (bool)$method->invoke(null, $result);
    }
}
