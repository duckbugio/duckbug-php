<?php

declare(strict_types=1);

namespace Unit;

use DuckBug\Core\Event;
use DuckBug\Core\EventAwareProvider;
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
        $duck = Duck::wake();
        self::assertInstanceOf(Duck::class, $duck);
        self::assertSame($duck, Duck::get());
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
        $duck = Duck::wake([$setup], ['password']);
        $duck->quack(new Exception('Test'));

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
        $duck = Duck::wake([$setup], ['password']);
        $duck->quack(new Exception('Test'));

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

        $duck = Duck::wake([
            new ProviderSetup($logger1, true),
            new ProviderSetup($logger2, true),
        ]);

        $duck->quack(new Exception('Test'));

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

            $duck = Duck::wake([$setup]);
            $duck->log(strtoupper($level), 'msg', ['a' => 1]);

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
        $duck = Duck::wake([$setup]);

        $duck->log('debug', 'should not log');
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
        $duck = Duck::wake([$setup]);
        $duck->log('unknownLevel', 'msg');

        self::assertFalse($logger->called);
    }

    public function testScopeMetadataIsSentToEventAwareProvider(): void
    {
        $provider = new class() implements Provider, EventAwareProvider {
            use LoggerTrait;

            /** @var Event|null */
            public $event;

            public function captureEvent(Event $event): void
            {
                $this->event = $event;
            }

            public function log($level, $message, array $context = []): void
            {
            }

            public function quack(Throwable $exception, array $context = []): void
            {
            }
        };

        $duck = Duck::wake([new ProviderSetup($provider)]);
        $duck
            ->setTag('module', 'billing')
            ->setRelease('1.2.3')
            ->setEnvironment('production')
            ->setRequestId('req-42')
            ->addBreadcrumb(['message' => 'step']);

        $duck->warning('Something went wrong');

        self::assertInstanceOf(Event::class, $provider->event);
        self::assertSame('logs', $provider->event->getType());
        self::assertSame('1.2.3', $provider->event->getPayload()['release']);
        self::assertSame('production', $provider->event->getPayload()['environment']);
        self::assertContains('module:billing', $provider->event->getPayload()['dTags']);
        self::assertSame('req-42', $provider->event->getPayload()['requestId']);
    }
}
