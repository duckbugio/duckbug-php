<?php

declare(strict_types=1);

namespace DuckBug\Core;

final class Event
{
    public const TYPE_LOG = 'logs';
    public const TYPE_ERROR = 'errors';
    public const TYPE_TRANSACTION = 'transactions';

    /** @var string */
    private $type;

    /** @var array<string, mixed> */
    private $payload;

    /** @param array<string, mixed> $payload */
    private function __construct(string $type, array $payload)
    {
        $this->type = $type;
        $this->payload = $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function log(array $payload): self
    {
        return new self(self::TYPE_LOG, $payload);
    }

    /** @param array<string, mixed> $payload */
    public static function error(array $payload): self
    {
        return new self(self::TYPE_ERROR, $payload);
    }

    /** @param array<string, mixed> $payload */
    public static function transaction(array $payload): self
    {
        return new self(self::TYPE_TRANSACTION, $payload);
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
