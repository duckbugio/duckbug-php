<?php

declare(strict_types=1);

namespace DuckBug\Core;

abstract class Event
{
    public const TYPE_LOG = 'logs';
    public const TYPE_ERROR = 'errors';
    public const TYPE_TRANSACTION = 'transactions';

    abstract public function getType(): string;

    /** @return array<string, mixed> */
    abstract public function getPayload(): array;

    /** @param array<string, mixed> $payload */
    final public static function log(array $payload): LogEvent
    {
        return LogEvent::fromPayload($payload);
    }

    /** @param array<string, mixed> $payload */
    final public static function error(array $payload): ErrorEvent
    {
        return ErrorEvent::fromPayload($payload);
    }

    /** @param array<string, mixed> $payload */
    final public static function transaction(array $payload): TransactionEvent
    {
        return TransactionEvent::fromPayload($payload);
    }
}
