<?php

declare(strict_types=1);

namespace DuckBug\Core;

final class Span
{
    /** @var string */
    private $traceId;
    /** @var string */
    private $spanId;
    /** @var string */
    private $parentSpanId;
    /** @var string */
    private $op;
    /** @var string */
    private $description;
    /** @var int */
    private $startTimestampMs;
    /** @var int|null */
    private $endTimestampMs;
    /** @var string|null */
    private $status;
    /** @var array<string, mixed> */
    private $data = [];

    /** @param array<string, mixed> $data */
    public function __construct(
        string $traceId,
        string $spanId,
        string $parentSpanId,
        string $op,
        string $description,
        int $startTimestampMs,
        array $data = []
    ) {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->parentSpanId = $parentSpanId;
        $this->op = trim($op) !== '' ? trim($op) : 'custom';
        $this->description = trim($description);
        $this->startTimestampMs = $startTimestampMs;
        $this->data = $data;
    }

    /** @param array<string, mixed> $data */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function finish(?string $status = null, ?int $endTimestampMs = null): self
    {
        $this->status = $status !== null ? trim($status) : $this->status;
        $this->endTimestampMs = $endTimestampMs !== null ? $endTimestampMs : (int)round(microtime(true) * 1000);

        return $this;
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        $endTimestampMs = $this->endTimestampMs !== null ? $this->endTimestampMs : $this->startTimestampMs;

        return [
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'parentSpanId' => $this->parentSpanId,
            'op' => $this->op,
            'description' => $this->description !== '' ? $this->description : null,
            'status' => $this->status,
            'startTime' => $this->startTimestampMs,
            'endTime' => $endTimestampMs,
            'duration' => max(0, $endTimestampMs - $this->startTimestampMs),
            'data' => !empty($this->data) ? $this->data : null,
        ];
    }
}
