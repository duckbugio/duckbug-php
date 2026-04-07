<?php

declare(strict_types=1);

namespace Unit;

use DuckBug\Core\ErrorEvent;
use DuckBug\Core\Event;
use DuckBug\Core\LogEvent;
use DuckBug\Core\Provider;
use DuckBug\Core\ProviderSetup;
use DuckBug\Duck;
use Exception;
use PHPUnit\Framework\TestCase;

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
            /** @var Event|null */
            public $event;

            public function captureEvent(Event $event): void
            {
                $this->event = $event;
            }
        };

        $setup = new ProviderSetup($logger, true);
        $duck = Duck::wake([$setup], ['password']);
        $duck->quack(new Exception('Test'));

        self::assertInstanceOf(Event::class, $logger->event);
        self::assertInstanceOf(ErrorEvent::class, $logger->event);
        self::assertSame(Event::TYPE_ERROR, $logger->event->getType());
    }

    public function testErrorEventCarriesTypedData(): void
    {
        $logger = new class() implements Provider {
            /** @var Event|null */
            public $event;

            public function captureEvent(Event $event): void
            {
                $this->event = $event;
            }
        };

        $duck = Duck::wake([new ProviderSetup($logger)]);
        $exception = new Exception('Typed error test');
        $duck->quack($exception, ['extra_key' => 'extra_val']);

        self::assertInstanceOf(ErrorEvent::class, $logger->event);
        /** @var ErrorEvent $errorEvent */
        $errorEvent = $logger->event;
        self::assertSame($exception, $errorEvent->getException());
        self::assertSame('Typed error test', $errorEvent->getMessage());
        self::assertSame($exception->getFile(), $errorEvent->getFile());
        self::assertSame($exception->getLine(), $errorEvent->getLine());
        self::assertSame(['extra_key' => 'extra_val'], $errorEvent->getContext());
        self::assertTrue($errorEvent->isHandled());
        self::assertSame('manual', $errorEvent->getMechanism());
        self::assertNotEmpty($errorEvent->getStacktrace());
        self::assertNotEmpty($errorEvent->getStacktraceAsString());
    }

    public function testQuackNotCalledIfDisabled(): void
    {
        $logger = new class() implements Provider {
            /** @var Event|null */
            public $event;

            public function captureEvent(Event $event): void
            {
                $this->event = $event;
            }
        };

        $setup = new ProviderSetup($logger, false);
        $duck = Duck::wake([$setup], ['password']);
        $duck->quack(new Exception('Test'));

        self::assertNull($logger->event);
    }

    public function testMultipleProvidersAreCalled(): void
    {
        $called = [];

        $makeLogger = function (string $id) use (&$called) {
            return new class($id, $called) implements Provider {
                private $id;
                private $calledRef;

                public function __construct($id, &$calledRef)
                {
                    $this->id = $id;
                    $this->calledRef = &$calledRef;
                }

                public function captureEvent(Event $event): void
                {
                    $this->calledRef[] = $this->id;
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
            'debug' => ['flag' => 'enabledDebug', 'normalized' => 'DEBUG'],
            'info' => ['flag' => 'enabledInfo', 'normalized' => 'INFO'],
            'notice' => ['flag' => 'enabledNotice', 'normalized' => 'INFO'],
            'warning' => ['flag' => 'enabledWarning', 'normalized' => 'WARN'],
            'error' => ['flag' => 'enabledError', 'normalized' => 'ERROR'],
            'critical' => ['flag' => 'enabledCritical', 'normalized' => 'FATAL'],
            'alert' => ['flag' => 'enabledAlert', 'normalized' => 'FATAL'],
            'emergency' => ['flag' => 'enabledEmergency', 'normalized' => 'FATAL'],
        ];

        foreach ($levels as $level => $settings) {
            $logger = new class() implements Provider {
                /** @var Event|null */
                public $event;

                public function captureEvent(Event $event): void
                {
                    $this->event = $event;
                }
            };

            $setup = new ProviderSetup(
                $logger,
                false,
                $settings['flag'] === 'enabledDebug',
                $settings['flag'] === 'enabledInfo',
                $settings['flag'] === 'enabledNotice',
                $settings['flag'] === 'enabledWarning',
                $settings['flag'] === 'enabledError',
                $settings['flag'] === 'enabledCritical',
                $settings['flag'] === 'enabledAlert',
                $settings['flag'] === 'enabledEmergency'
            );

            $duck = Duck::wake([$setup]);
            $duck->log(strtoupper($level), 'msg', ['a' => 1]);

            self::assertInstanceOf(LogEvent::class, $logger->event);
            self::assertSame(Event::TYPE_LOG, $logger->event->getType());

            /** @var LogEvent $logEvent */
            $logEvent = $logger->event;
            self::assertSame($settings['normalized'], $logEvent->getLevel());
            self::assertSame('msg', $logEvent->getMessage());
            self::assertSame(['a' => 1], $logEvent->getContext());

            self::assertSame($settings['normalized'], $logEvent->getPayload()['level']);
            self::assertSame('msg', $logEvent->getPayload()['message']);
        }
    }

    public function testLogSkipsWhenLevelDisabled(): void
    {
        $logger = new class() implements Provider {
            /** @var Event|null */
            public $event;

            public function captureEvent(Event $event): void
            {
                $this->event = $event;
            }
        };

        $setup = new ProviderSetup($logger, false, false, false, false, false, false, false, false, false);
        $duck = Duck::wake([$setup]);

        $duck->log('debug', 'should not log');
        self::assertNull($logger->event);
    }

    public function testUnknownLogLevelIsIgnored(): void
    {
        $logger = new class() implements Provider {
            /** @var Event|null */
            public $event;

            public function captureEvent(Event $event): void
            {
                $this->event = $event;
            }
        };

        $setup = new ProviderSetup($logger);
        $duck = Duck::wake([$setup]);
        $duck->log('unknownLevel', 'msg');

        self::assertNull($logger->event);
    }

    public function testScopeMetadataIsSentToProvider(): void
    {
        $provider = new class() implements Provider {
            /** @var Event|null */
            public $event;

            public function captureEvent(Event $event): void
            {
                $this->event = $event;
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
