<?php

declare(strict_types=1);

namespace DuckBug\Core;

final class TransactionEvent extends Event
{
    /** @var Transaction|null */
    private $transaction;

    /** @var array<string, mixed> */
    private $payload;

    /** @param array<string, mixed> $payload */
    public function __construct(?Transaction $transaction, array $payload)
    {
        $this->transaction = $transaction;
        $this->payload = $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(null, $payload);
    }

    public function getType(): string
    {
        return self::TYPE_TRANSACTION;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }
}
