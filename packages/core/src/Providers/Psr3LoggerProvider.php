<?php

declare(strict_types=1);

namespace DuckBug\Providers;

use DuckBug\Core\ErrorEvent;
use DuckBug\Core\Event;
use DuckBug\Core\LogEvent;
use DuckBug\Core\Provider;
use Psr\Log\LoggerInterface;

final class Psr3LoggerProvider implements Provider
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function captureEvent(Event $event): void
    {
        if ($event instanceof ErrorEvent) {
            $context = $event->getContext();
            $exception = $event->getException();
            if ($exception !== null) {
                $context['exception'] = $exception;
            }
            $this->logger->error($event->getMessage(), $context);
            return;
        }

        if ($event instanceof LogEvent) {
            $this->logger->log(
                $this->mapLevel($event->getLevel()),
                $event->getMessage(),
                $event->getContext()
            );
        }
    }

    private function mapLevel(string $duckBugLevel): string
    {
        switch ($duckBugLevel) {
            case 'DEBUG':
                return 'debug';
            case 'INFO':
                return 'info';
            case 'WARN':
                return 'warning';
            case 'ERROR':
                return 'error';
            case 'FATAL':
                return 'critical';
            default:
                return 'info';
        }
    }
}
