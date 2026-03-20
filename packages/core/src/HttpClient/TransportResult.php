<?php

declare(strict_types=1);

namespace DuckBug\HttpClient;

final class TransportResult
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $responseBody;

    /** @var string|null */
    private $errorMessage;

    /** @var int */
    private $attempts;

    public function __construct(int $statusCode, string $responseBody = '', ?string $errorMessage = null, int $attempts = 1)
    {
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
        $this->errorMessage = $errorMessage;
        $this->attempts = $attempts;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300 && $this->errorMessage === null;
    }
}
