<?php

declare(strict_types=1);

namespace Unit\Providers;

use DuckBug\Core\Event;
use DuckBug\HttpClient\HttpClientInterface;
use DuckBug\HttpClient\TransportResult;
use DuckBug\Providers\DuckBugProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * @internal
 */
final class DuckBugProviderTest extends TestCase
{
    private const TEST_DSN = 'https://duckbug.io';

    /**
     * @throws ReflectionException
     */
    public function testCaptureEventSendsLogPayload()
    {
        /** @var HttpClientInterface|MockObject $client */
        $client = $this->createMock(HttpClientInterface::class);

        /** @noinspection PhpParamsInspection */
        $client->expects(self::once())
            ->method('send')
            ->with(
                self::TEST_DSN,
                'logs',
                self::callback(function (array $data) {
                    self::assertArrayHasKey('eventId', $data);
                    self::assertSame('Something went wrong', $data['message']);
                    self::assertSame('WARN', $data['level']);
                    self::assertIsInt($data['time']);
                    self::assertSame(['foo' => 'bar'], $data['context']);
                    return true;
                })
            )
            ->willReturn(new TransportResult(202));

        $provider = $this->createProviderWithClient($client);

        $provider->captureEvent(Event::log([
            'eventId' => '550e8400-e29b-41d4-a716-446655440000',
            'time' => 1704067200000,
            'level' => 'WARN',
            'message' => 'Something went wrong',
            'context' => ['foo' => 'bar'],
        ]));
    }

    /**
     * @throws ReflectionException
     */
    public function testCaptureEventSendsErrorPayload()
    {
        /** @var HttpClientInterface|MockObject $client */
        $client = $this->createMock(HttpClientInterface::class);

        /** @noinspection PhpParamsInspection */
        $client->expects(self::once())
            ->method('send')
            ->with(
                self::TEST_DSN,
                'errors',
                self::callback(function (array $data) {
                    self::assertTrue($data['handled']);
                    self::assertSame('manual', $data['mechanism']);
                    return isset($data['eventId'], $data['message'], $data['stacktrace'], $data['file'], $data['line']);
                })
            )
            ->willReturn(new TransportResult(202));

        $provider = $this->createProviderWithClient($client);

        $provider->captureEvent(Event::error([
            'eventId' => '550e8400-e29b-41d4-a716-446655440001',
            'time' => 1704067200000,
            'file' => '/srv/app/test.php',
            'line' => 42,
            'message' => 'test',
            'stacktrace' => [['file' => '/srv/app/test.php', 'line' => 42]],
            'handled' => true,
            'mechanism' => 'manual',
        ]));
    }

    /**
     * @throws ReflectionException
     */
    public function testFlushUsesBatchEndpoint(): void
    {
        /** @var HttpClientInterface|MockObject $client */
        $client = $this->createMock(HttpClientInterface::class);

        $client->expects(self::once())
            ->method('sendBatch')
            ->with(
                self::TEST_DSN,
                'logs',
                self::callback(function (array $items) {
                    self::assertCount(2, $items);
                    self::assertSame('First', $items[0]['message']);
                    self::assertSame('Second', $items[1]['message']);
                    return true;
                })
            )
            ->willReturn(new TransportResult(202));

        $provider = $this->createProviderWithClient($client, 2);

        $provider->captureEvent(Event::log([
            'eventId' => '550e8400-e29b-41d4-a716-446655440010',
            'time' => 1704067200000,
            'level' => 'WARN',
            'message' => 'First',
            'context' => [],
        ]));
        $provider->captureEvent(Event::log([
            'eventId' => '550e8400-e29b-41d4-a716-446655440011',
            'time' => 1704067200001,
            'level' => 'WARN',
            'message' => 'Second',
            'context' => [],
        ]));
    }

    /**
     * @throws ReflectionException
     */
    public function testTransactionBypassesBatchBufferAndUsesSingleIngest(): void
    {
        /** @var HttpClientInterface|MockObject $client */
        $client = $this->createMock(HttpClientInterface::class);

        $client->expects(self::once())
            ->method('send')
            ->with(
                self::TEST_DSN,
                'transactions',
                self::callback(function (array $data) {
                    self::assertSame('trace-123', $data['traceId']);
                    self::assertSame('transaction-123', $data['transaction']);
                    return true;
                })
            )
            ->willReturn(new TransportResult(202));

        $client->expects(self::never())->method('sendBatch');

        $provider = $this->createProviderWithClient($client, 10);

        $provider->captureEvent(Event::transaction([
            'eventId' => '550e8400-e29b-41d4-a716-446655440000',
            'traceId' => 'trace-123',
            'spanId' => 'span-123',
            'transaction' => 'transaction-123',
            'op' => 'http.server',
            'startTime' => 1704067200000,
            'endTime' => 1704067200100,
            'duration' => 100,
        ]));
    }

    /**
     * @throws ReflectionException
     */
    private function createProviderWithClient(HttpClientInterface $client, int $batchSize = 1): DuckBugProvider
    {
        $provider = DuckBugProvider::create(
            self::TEST_DSN,
            false,
            true,
            1,
            1,
            $batchSize
        );
        $this->injectClient($provider, $client);
        return $provider;
    }

    /**
     * @param mixed $provider
     * @param mixed $client
     * @throws ReflectionException
     */
    private function injectClient($provider, $client)
    {
        $reflection = new ReflectionClass($provider);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($provider, $client);
    }
}
