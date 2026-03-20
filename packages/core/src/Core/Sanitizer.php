<?php

declare(strict_types=1);

namespace DuckBug\Core;

final class Sanitizer
{
    /** @var string[] */
    private $sensitiveFields;

    /** @var string[] */
    private $sensitiveHeaderNames;

    /** @var string */
    private $mask;

    /**
     * @param string[] $sensitiveFields
     * @param string[] $sensitiveHeaderNames
     */
    public function __construct(
        array $sensitiveFields = [],
        array $sensitiveHeaderNames = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token'],
        string $mask = '***'
    ) {
        $this->sensitiveFields = array_map('strtolower', $sensitiveFields);
        $this->sensitiveHeaderNames = array_map('strtolower', $sensitiveHeaderNames);
        $this->mask = $mask;
    }

    /** @param array<string, mixed>|null $data */
    public function sanitizeMap(?array $data): ?array
    {
        if (empty($data)) {
            return null;
        }

        return $this->sanitizeArray($data, false);
    }

    /** @param array<string, mixed>|null $headers */
    public function sanitizeHeaders(?array $headers): ?array
    {
        if (empty($headers)) {
            return null;
        }

        return $this->sanitizeArray($headers, true);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function sanitizeArray(array $data, bool $headerMode): array
    {
        /** @var mixed $value */
        foreach ($data as $key => $value) {
            $normalizedKey = \is_string($key) ? strtolower($key) : '';

            if ($normalizedKey !== '' && $this->shouldMaskKey($normalizedKey, $headerMode)) {
                $data[$key] = $this->mask;
                continue;
            }

            if (\is_array($value)) {
                $data[$key] = $this->sanitizeArray($value, false);
            }
        }

        return $data;
    }

    private function shouldMaskKey(string $key, bool $headerMode): bool
    {
        if ($headerMode && \in_array($key, $this->sensitiveHeaderNames, true)) {
            return true;
        }

        return \in_array($key, $this->sensitiveFields, true);
    }
}
