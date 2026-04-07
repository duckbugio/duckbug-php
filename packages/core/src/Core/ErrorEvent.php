<?php

declare(strict_types=1);

namespace DuckBug\Core;

use Throwable;

final class ErrorEvent extends Event
{
    /** @var Throwable|null */
    private $exception;

    /** @var array<string, mixed> */
    private $context;

    /** @var bool */
    private $handled;

    /** @var string */
    private $mechanism;

    /** @var array<string, mixed> */
    private $payload;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     */
    public function __construct(
        ?Throwable $exception,
        array $context,
        bool $handled,
        string $mechanism,
        array $payload
    ) {
        $this->exception = $exception;
        $this->context = $context;
        $this->handled = $handled;
        $this->mechanism = $mechanism;
        $this->payload = $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        /** @var array<string, mixed> $context */
        $context = isset($payload['context']) && \is_array($payload['context']) ? $payload['context'] : [];

        return new self(
            null,
            $context,
            isset($payload['handled']) ? (bool)$payload['handled'] : true,
            isset($payload['mechanism']) ? (string)$payload['mechanism'] : 'manual',
            $payload
        );
    }

    public function getType(): string
    {
        return self::TYPE_ERROR;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    public function getMessage(): string
    {
        return (string)($this->payload['message'] ?? '');
    }

    public function getFile(): string
    {
        return (string)($this->payload['file'] ?? '');
    }

    public function getLine(): int
    {
        return (int)($this->payload['line'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStacktrace(): array
    {
        /** @var array<int, array<string, mixed>> */
        return isset($this->payload['stacktrace']) && \is_array($this->payload['stacktrace'])
            ? $this->payload['stacktrace']
            : [];
    }

    public function getStacktraceAsString(): string
    {
        return (string)($this->payload['stacktraceAsString'] ?? '');
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function getMechanism(): string
    {
        return $this->mechanism;
    }
}
