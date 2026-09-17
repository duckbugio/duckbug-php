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
     * The server's own `omitempty,uuid4` rule spelled out. Ingest answers 400 to
     * anything else, so an id minted here has to match it or the event is dropped
     * instead of deduplicated.
     */
    private const UUID4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

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
    public function testMintsEventIdWhenCallerOmitsIt(): void
    {
        $sent = $this->captureSentLogPayload([
            'time' => 1704067200000,
            'level' => 'WARN',
            'message' => 'no id from the caller',
            'context' => [],
        ]);

        self::assertArrayHasKey('eventId', $sent);
        self::assertSame(1, preg_match(self::UUID4_PATTERN, (string)$sent['eventId']));
    }

    /**
     * @throws ReflectionException
     */
    public function testReplacesEventIdIngestWouldReject(): void
    {
        /** @var array<string, mixed> $unusable */
        $unusable = [
            'empty string' => '',
            'whitespace only' => "  \t ",
            'not a string' => 12345,
            'boolean' => true,
        ];

        /** @var mixed $value */
        foreach ($unusable as $label => $value) {
            $sent = $this->captureSentLogPayload([
                'eventId' => $value,
                'time' => 1704067200000,
                'level' => 'WARN',
                'message' => 'unusable id from the caller',
                'context' => [],
            ]);

            self::assertArrayHasKey('eventId', $sent, $label);
            self::assertSame(1, preg_match(self::UUID4_PATTERN, (string)$sent['eventId']), $label);
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testKeepsEventIdSuppliedByTheCaller(): void
    {
        $sent = $this->captureSentLogPayload([
            'eventId' => '550e8400-e29b-41d4-a716-446655440020',
            'time' => 1704067200000,
            'level' => 'WARN',
            'message' => 'caller owns the id',
            'context' => [],
        ]);

        self::assertSame('550e8400-e29b-41d4-a716-446655440020', $sent['eventId']);
    }

    /**
     * A beforeSend hook runs before the id is filled in, so one that drops the
     * field would reopen the gap if the provider trusted the hook's output.
     *
     * @throws ReflectionException
     */
    public function testRestoresEventIdDroppedByBeforeSend(): void
    {
        $sent = $this->captureSentLogPayload(
            [
                'eventId' => '550e8400-e29b-41d4-a716-446655440021',
                'time' => 1704067200000,
                'level' => 'WARN',
                'message' => 'beforeSend dropped the id',
                'context' => [],
            ],
            static function (array $payload): array {
                unset($payload['eventId']);
                return $payload;
            }
        );

        self::assertArrayHasKey('eventId', $sent);
        self::assertSame(1, preg_match(self::UUID4_PATTERN, (string)$sent['eventId']));
        self::assertNotSame('550e8400-e29b-41d4-a716-446655440021', $sent['eventId']);
    }

    /**
     * Filling the id must not resurrect an event a beforeSend hook dropped, nor
     * turn one into a payload whose only field is the id.
     *
     * @throws ReflectionException
     */
    public function testBeforeSendDiscardingTheEventStillSendsNothing(): void
    {
        /** @var HttpClientInterface|MockObject $client */
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::never())->method('send');
        $client->expects(self::never())->method('sendBatch');

        $provider = $this->createProviderWithClient($client);
        $provider->setBeforeSend(static function (array $payload) {
            return null;
        });

        $provider->captureEvent(Event::log([
            'time' => 1704067200000,
            'level' => 'WARN',
            'message' => 'dropped by the hook',
            'context' => [],
        ]));
    }

    /**
     * @throws ReflectionException
     */
    public function testMintsDistinctEventIdsPerEvent(): void
    {
        /** @var HttpClientInterface|MockObject $client */
        $client = $this->createMock(HttpClientInterface::class);

        $batch = [];
        $client->expects(self::once())
            ->method('sendBatch')
            ->willReturnCallback(static function (string $dsn, string $type, array $items) use (&$batch) {
                $batch = $items;
                return new TransportResult(202);
            });

        $provider = $this->createProviderWithClient($client, 2);

        for ($i = 0; $i < 2; ++$i) {
            $provider->captureEvent(Event::log([
                'time' => 1704067200000 + $i,
                'level' => 'WARN',
                'message' => 'batched without an id',
                'context' => [],
            ]));
        }

        self::assertCount(2, $batch);
        self::assertSame(1, preg_match(self::UUID4_PATTERN, (string)$batch[0]['eventId']));
        self::assertSame(1, preg_match(self::UUID4_PATTERN, (string)$batch[1]['eventId']));
        self::assertNotSame($batch[0]['eventId'], $batch[1]['eventId']);
    }

    /**
     * @param array<string, mixed> $payload
     * @throws ReflectionException
     * @return array<string, mixed>
     */
    private function captureSentLogPayload(array $payload, ?callable $beforeSend = null): array
    {
        /** @var HttpClientInterface|MockObject $client */
        $client = $this->createMock(HttpClientInterface::class);

        $sent = [];
        $client->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (string $dsn, string $type, array $data) use (&$sent) {
                $sent = $data;
                return new TransportResult(202);
            });

        $provider = $this->createProviderWithClient($client);
        if ($beforeSend !== null) {
            $provider->setBeforeSend($beforeSend);
        }

        $provider->captureEvent(Event::log($payload));

        return $sent;
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
