<?php

declare(strict_types=1);

namespace DuckBug\Core;

final class Transaction
{
    /** @var string */
    private $name;
    /** @var string */
    private $op;
    /** @var string */
    private $traceId;
    /** @var string */
    private $spanId;
    /** @var int */
    private $startTimestampMs;
    /** @var int|null */
    private $endTimestampMs;
    /** @var string|null */
    private $status;
    /** @var array<string, mixed>|null */
    private $context;
    /** @var array<string, mixed> */
    private $measurements = [];
    /** @var array<int, Span> */
    private $spans = [];
    /** @var callable */
    private $spanIdGenerator;

    public function __construct(
        string $name,
        string $op,
        string $traceId,
        string $spanId,
        int $startTimestampMs,
        callable $spanIdGenerator
    ) {
        $this->name = trim($name) !== '' ? trim($name) : 'transaction';
        $this->op = trim($op) !== '' ? trim($op) : 'custom';
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->startTimestampMs = $startTimestampMs;
        $this->spanIdGenerator = $spanIdGenerator;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOp(): string
    {
        return $this->op;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getStartTimestampMs(): int
    {
        return $this->startTimestampMs;
    }

    public function getEndTimestampMs(): ?int
    {
        return $this->endTimestampMs;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /** @return array<string, mixed>|null */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /** @return array<string, mixed> */
    public function getMeasurements(): array
    {
        return $this->measurements;
    }

    /** @return array<int, Span> */
    public function getSpans(): array
    {
        return $this->spans;
    }

    /** @param array<string, mixed> $context */
    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @param float|int $value
     */
    public function addMeasurement(string $key, $value, ?string $unit = null): self
    {
        $key = trim($key);
        if ($key === '') {
            return $this;
        }

        $measurement = ['value' => $value];
        if ($unit !== null && trim($unit) !== '') {
            $measurement['unit'] = trim($unit);
        }

        $this->measurements[$key] = $measurement;

        return $this;
    }

    public function startChild(string $op, string $description = ''): Span
    {
        $span = new Span(
            $this->traceId,
            (string)\call_user_func($this->spanIdGenerator),
            $this->spanId,
            $op,
            $description,
            (int)round(microtime(true) * 1000)
        );

        $this->spans[] = $span;

        return $span;
    }

    public function finish(?string $status = null, ?int $endTimestampMs = null): self
    {
        $this->status = $status !== null ? trim($status) : $this->status;
        $this->endTimestampMs = $endTimestampMs !== null ? $endTimestampMs : (int)round(microtime(true) * 1000);

        return $this;
    }

    public function getDurationMs(): int
    {
        $endTimestampMs = $this->endTimestampMs !== null ? $this->endTimestampMs : $this->startTimestampMs;

        return max(0, $endTimestampMs - $this->startTimestampMs);
    }
}
