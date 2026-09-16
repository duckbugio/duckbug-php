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

    public function testUnknownServerFailuresStayRetriable(): void
    {
        foreach ([505, 507, 508, 510, 521, 599] as $statusCode) {
            self::assertTrue(
                self::isRetriable(new TransportResult($statusCode)),
                sprintf(
                    'Status %d is one the SDK has not been taught about; an edge in front of the '
                    . 'installation invents those, and dropping the event on the first one loses it silently',
                    $statusCode
                )
            );
        }
    }

    public function testRequestTimeoutIsRetried(): void
    {
        self::assertTrue(
            self::isRetriable(new TransportResult(408)),
            'Status 408 is the edge timing out the request body, not a verdict on the payload, '
            . 'and RFC 9110 states such a request may be repeated unchanged'
        );
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
            'Status 501 means the capability is not configured in this installation, '
            . 'so repeating the request cannot change the answer'
        );
    }

    public function testClientErrorsAreTerminal(): void
    {
        foreach ([400, 401, 403, 404, 409, 413, 415, 422] as $statusCode) {
            self::assertFalse(
                self::isRetriable(new TransportResult($statusCode)),
                sprintf(
                    'Status %d is a verdict on this exact request - rejected, already ingested or '
                    . 'malformed - and sending it again cannot change it. 408 is the one 4xx that can',
                    $statusCode
                )
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
