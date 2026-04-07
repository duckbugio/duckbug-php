<?php

declare(strict_types=1);

namespace DuckBug\Core;

final class LogEvent extends Event
{
    /** @var string */
    private $level;

    /** @var string */
    private $message;

    /** @var array<string, mixed> */
    private $context;

    /** @var array<string, mixed> */
    private $payload;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     */
    public function __construct(string $level, string $message, array $context, array $payload)
    {
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->payload = $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        /** @var array<string, mixed> $context */
        $context = isset($payload['context']) && \is_array($payload['context']) ? $payload['context'] : [];

        return new self(
            (string)($payload['level'] ?? 'INFO'),
            (string)($payload['message'] ?? ''),
            $context,
            $payload
        );
    }

    public function getType(): string
    {
        return self::TYPE_LOG;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }
}
