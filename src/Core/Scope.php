<?php

declare(strict_types=1);

namespace DuckBug\Core;

final class Scope
{
    /** @var string[] */
    private $tags = [];

    /** @var array<string, mixed>|null */
    private $user;

    /** @var string|null */
    private $release;

    /** @var string|null */
    private $environment;

    /** @var string|null */
    private $dist;

    /** @var string|null */
    private $serverName;

    /** @var string|null */
    private $service;

    /** @var string|null */
    private $requestId;

    /** @var string|null */
    private $transaction;

    /** @var string|null */
    private $traceId;

    /** @var string|null */
    private $spanId;

    /** @var string|null */
    private $fingerprint;

    /** @var array<int, array<string, mixed>> */
    private $breadcrumbs = [];

    /** @var array<string, mixed> */
    private $sdk = [];

    /** @var array<string, mixed> */
    private $runtime = [];

    /** @var array<string, mixed> */
    private $extra = [];

    /** @var string */
    private $platform = 'php';

    /**
     * @param mixed $value
     */
    public function setTag(string $key, $value): self
    {
        $key = trim($key);
        if ($key === '') {
            return $this;
        }

        $normalized = $value === null
            ? $key
            : $key . ':' . trim((string)$value);

        $this->tags[$key] = $normalized;

        return $this;
    }

    /**
     * @param array<string, scalar|null> $tags
     */
    public function setTags(array $tags): self
    {
        foreach ($tags as $key => $value) {
            $this->setTag($key, $value);
        }

        return $this;
    }

    /** @return string[] */
    public function getTags(): array
    {
        return array_values($this->tags);
    }

    public function clearTags(): self
    {
        $this->tags = [];

        return $this;
    }

    /** @param array<string, mixed>|null $user */
    public function setUser(?array $user): self
    {
        $this->user = $user;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getUser(): ?array
    {
        return $this->user;
    }

    public function setRelease(?string $release): self
    {
        $this->release = $this->normalizeString($release);

        return $this;
    }

    public function getRelease(): ?string
    {
        return $this->release;
    }

    public function setEnvironment(?string $environment): self
    {
        $this->environment = $this->normalizeString($environment);

        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setDist(?string $dist): self
    {
        $this->dist = $this->normalizeString($dist);

        return $this;
    }

    public function getDist(): ?string
    {
        return $this->dist;
    }

    public function setServerName(?string $serverName): self
    {
        $this->serverName = $this->normalizeString($serverName);

        return $this;
    }

    public function getServerName(): ?string
    {
        return $this->serverName;
    }

    public function setService(?string $service): self
    {
        $this->service = $this->normalizeString($service);

        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $this->normalizeString($requestId);

        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setTransaction(?string $transaction): self
    {
        $this->transaction = $this->normalizeString($transaction);

        return $this;
    }

    public function getTransaction(): ?string
    {
        return $this->transaction;
    }

    public function setTrace(?string $traceId, ?string $spanId = null): self
    {
        $this->traceId = $this->normalizeString($traceId);
        $this->spanId = $this->normalizeString($spanId);

        return $this;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function getSpanId(): ?string
    {
        return $this->spanId;
    }

    public function setFingerprint(?string $fingerprint): self
    {
        $this->fingerprint = $this->normalizeString($fingerprint);

        return $this;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    /** @param array<string, mixed> $sdk */
    public function setSDK(array $sdk): self
    {
        $this->sdk = $sdk;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSDK(): array
    {
        return $this->sdk;
    }

    /** @param array<string, mixed> $runtime */
    public function setRuntime(array $runtime): self
    {
        $this->runtime = $runtime;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getRuntime(): array
    {
        return $this->runtime;
    }

    /** @param array<string, mixed> $extra */
    public function setExtra(array $extra): self
    {
        $this->extra = $extra;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getExtra(): array
    {
        return $this->extra;
    }

    public function setPlatform(string $platform): self
    {
        $platform = trim($platform);
        if ($platform !== '') {
            $this->platform = $platform;
        }

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    /** @param array<string, mixed> $breadcrumb */
    public function addBreadcrumb(array $breadcrumb): self
    {
        if (!isset($breadcrumb['timestamp'])) {
            $breadcrumb['timestamp'] = (int)round(microtime(true) * 1000);
        }

        $this->breadcrumbs[] = $breadcrumb;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    public function clearBreadcrumbs(): self
    {
        $this->breadcrumbs = [];

        return $this;
    }

    public function clearUser(): self
    {
        $this->user = null;

        return $this;
    }

    /**
     * @return array<string, mixed>
     * @psalm-return array{
     *     platform: string,
     *     release: string|null,
     *     environment: string|null,
     *     dist: string|null,
     *     serverName: string|null,
     *     service: string|null,
     *     requestId: string|null,
     *     transaction: string|null,
     *     traceId: string|null,
     *     spanId: string|null,
     *     fingerprint: string|null,
     *     dTags: array<array-key, string>,
     *     sdk: array<string, mixed>,
     *     runtime: array<string, mixed>,
     *     extra: array<string, mixed>,
     *     breadcrumbs: array<int, array<string, mixed>>,
     *     user: array<string, mixed>|null
     * }
     */
    public function toMetadata(): array
    {
        return [
            'platform' => $this->platform,
            'release' => $this->release,
            'environment' => $this->environment,
            'dist' => $this->dist,
            'serverName' => $this->serverName,
            'service' => $this->service,
            'requestId' => $this->requestId,
            'transaction' => $this->transaction,
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'fingerprint' => $this->fingerprint,
            'dTags' => $this->getTags(),
            'sdk' => $this->sdk,
            'runtime' => $this->runtime,
            'extra' => $this->extra,
            'breadcrumbs' => $this->breadcrumbs,
            'user' => $this->user,
        ];
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
