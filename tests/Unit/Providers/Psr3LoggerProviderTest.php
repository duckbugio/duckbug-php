<?php

declare(strict_types=1);

namespace Unit\Providers;

use DuckBug\Core\ErrorEvent;
use DuckBug\Core\Event;
use DuckBug\Providers\Psr3LoggerProvider;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * @internal
 */
final class Psr3LoggerProviderTest extends TestCase
{
    public function testLogEventIsForwardedWithMappedLevel(): void
    {
        $spy = $this->createSpyLogger();
        $provider = new Psr3LoggerProvider($spy);

        $provider->captureEvent(Event::log([
            'level' => 'WARN',
            'message' => 'disk almost full',
            'context' => ['usage' => '95%'],
        ]));

        self::assertCount(1, $spy->records);
        self::assertSame('warning', $spy->records[0]['level']);
        self::assertSame('disk almost full', $spy->records[0]['message']);
        self::assertSame(['usage' => '95%'], $spy->records[0]['context']);
    }

    public function testErrorEventIsForwardedAsError(): void
    {
        $spy = $this->createSpyLogger();
        $provider = new Psr3LoggerProvider($spy);
        $exception = new Exception('Something broke');

        $provider->captureEvent(new ErrorEvent(
            $exception,
            ['requestId' => 'abc'],
            true,
            'manual',
            [
                'message' => 'Something broke',
                'file' => __FILE__,
                'line' => __LINE__,
                'stacktrace' => [],
            ]
        ));

        self::assertCount(1, $spy->records);
        self::assertSame('error', $spy->records[0]['level']);
        self::assertSame('Something broke', $spy->records[0]['message']);
        self::assertSame($exception, $spy->records[0]['context']['exception']);
        self::assertSame('abc', $spy->records[0]['context']['requestId']);
    }

    public function testErrorEventWithoutExceptionOmitsExceptionKey(): void
    {
        $spy = $this->createSpyLogger();
        $provider = new Psr3LoggerProvider($spy);

        $provider->captureEvent(Event::error([
            'message' => 'raw error',
            'file' => '/app/test.php',
            'line' => 1,
            'stacktrace' => [],
            'context' => ['key' => 'val'],
        ]));

        self::assertCount(1, $spy->records);
        self::assertArrayNotHasKey('exception', $spy->records[0]['context']);
        self::assertSame('val', $spy->records[0]['context']['key']);
    }

    public function testTransactionEventIsIgnored(): void
    {
        $spy = $this->createSpyLogger();
        $provider = new Psr3LoggerProvider($spy);

        $provider->captureEvent(Event::transaction([
            'traceId' => 't-1',
            'transaction' => 'test',
        ]));

        self::assertCount(0, $spy->records);
    }

    /**
     * @return object&AbstractLogger
     */
    private function createSpyLogger()
    {
        return new class() extends AbstractLogger {
            /** @var array<int, array{level: string, message: string, context: array<string, mixed>}> */
            public $records = [];

            /**
             * @param mixed $level
             * @param string $message
             * @param array<string, mixed> $context
             */
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string)$level,
                    'message' => (string)$message,
                    'context' => $context,
                ];
            }
        };
    }
}
