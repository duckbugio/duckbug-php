<?php

declare(strict_types=1);

namespace Unit;

use DuckBug\Core\Provider;
use DuckBug\Core\ProviderSetup;
use DuckBug\Duck;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerTrait;
use Throwable;

/**
 * @internal
 */
final class DuckBugTest extends TestCase
{
    public function testSingletonInstance(): void
    {
        $instance = Duck::wake();
        self::assertInstanceOf(Duck::class, $instance);
        self::assertSame($instance, Duck::get());
    }

    public function testQuackCalledIfEnabled(): void
    {
        $logger = new class() implements Provider {
            use LoggerTrait;

            public $called = false;

            public function quack(Throwable $exception, array $context = []): void
            {
                $this->called = true;
            }

            public function log($level, $message, array $context = []): void
            {
            }
        };

        $setup = new ProviderSetup($logger, true);
        $duckBug = new Duck([$setup]);
        $duckBug->quack(new Exception('Test'));

        self::assertTrue($logger->called);
    }

    public function testQuackNotCalledIfDisabled(): void
    {
        $logger = new class() implements Provider {
            use LoggerTrait;
            public $called = false;

            public function quack(Throwable $exception, array $context = []): void
            {
                $this->called = true;
            }

            public function log($level, $message, array $context = []): void
            {
            }
        };

        $setup = new ProviderSetup($logger, false);
        $duckBug = new Duck([$setup]);
        $duckBug->quack(new Exception('Test'));

        self::assertFalse($logger->called);
    }

    public function testMultipleProvidersAreCalled(): void
    {
        $called = [];

        $makeLogger = function (string $id) use (&$called) {
            return new class($id, $called) implements Provider {
                use LoggerTrait;

                private $id;
                private $calledRef;

                public function __construct($id, &$calledRef)
                {
                    $this->id = $id;
                    $this->calledRef = &$calledRef;
                }

                public function quack(Throwable $exception, array $context = []): void
                {
                    $this->calledRef[] = $this->id;
                }

                public function log($level, $message, array $context = []): void
                {
                }
            };
        };

        $logger1 = $makeLogger('A');
        $logger2 = $makeLogger('B');

        $duckBug = new Duck([
            new ProviderSetup($logger1, true),
            new ProviderSetup($logger2, true),
        ]);

        $duckBug->quack(new Exception('Test'));

        self::assertEquals(['A', 'B'], $called);
    }

    public function testLogRespectsLevelFlags(): void
    {
        $levels = [
            'debug' => 'enabledDebug',
            'info' => 'enabledInfo',
            'notice' => 'enabledNotice',
            'warning' => 'enabledWarning',
            'error' => 'enabledError',
            'critical' => 'enabledCritical',
            'alert' => 'enabledAlert',
            'emergency' => 'enabledEmergency',
        ];

        foreach ($levels as $level => $flag) {
            $logger = new class() implements Provider {
                use LoggerTrait;

                public $lastLevel;
                public $lastMessage;
                public $lastContext;

                public function log($level, $message, array $context = []): void
                {
                    $this->lastLevel = $level;
                    $this->lastMessage = $message;
                    $this->lastContext = $context;
                }

                public function quack(Throwable $exception, array $context = []): void
                {
                }
            };

            $setup = new ProviderSetup(
                $logger,
                false,
                $flag === 'enabledDebug',
                $flag === 'enabledInfo',
                $flag === 'enabledNotice',
                $flag === 'enabledWarning',
                $flag === 'enabledError',
                $flag === 'enabledCritical',
                $flag === 'enabledAlert',
                $flag === 'enabledEmergency'
            );

            $duckBug = new Duck([$setup]);
            $duckBug->log(strtoupper($level), 'msg', ['a' => 1]);

            self::assertSame(strtoupper($level), $logger->lastLevel);
            self::assertSame('msg', $logger->lastMessage);
            self::assertSame(['a' => 1], $logger->lastContext);
        }
    }

    public function testLogSkipsWhenLevelDisabled(): void
    {
        $logger = new class() implements Provider {
            use LoggerTrait;

            public $called = false;

            public function log($level, $message, array $context = []): void
            {
                $this->called = true;
            }

            public function quack(Throwable $exception, array $context = []): void
            {
            }
        };

        $setup = new ProviderSetup($logger, false, false, false, false, false, false, false, false, false);
        $duckBug = new Duck([$setup]);

        $duckBug->log('debug', 'should not log');
        self::assertFalse($logger->called);
    }

    public function testUnknownLogLevelIsIgnored(): void
    {
        $logger = new class() implements Provider {
            use LoggerTrait;
            public $called = false;

            public function log($level, $message, array $context = []): void
            {
                $this->called = true;
            }

            public function quack(Throwable $exception, array $context = []): void
            {
            }
        };

        $setup = new ProviderSetup($logger);
        $duckBug = new Duck([$setup]);
        $duckBug->log('unknownLevel', 'msg');

        self::assertFalse($logger->called);
    }
}
