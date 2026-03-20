<?php

declare(strict_types=1);

namespace DuckBug;

use DuckBug\Core\Sanitizer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;

final class Pond
{
    /** @var Sanitizer */
    private $sanitizer;
    /** @var ServerRequestInterface|null */
    private $request;

    /** @param string[] $sensitiveFields */
    private function __construct(array $sensitiveFields)
    {
        $this->sanitizer = new Sanitizer(array_merge(
            ['password', 'token', 'api_key', 'authorization', 'cookie', 'session', 'secret'],
            $sensitiveFields
        ));
    }

    /** @param string[] $sensitiveFields */
    public static function ripple(array $sensitiveFields = []): self
    {
        return new self($sensitiveFields);
    }

    public function getRequest(): ServerRequestInterface
    {
        if ($this->request instanceof ServerRequestInterface) {
            return $this->request;
        }

        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        return $creator->fromGlobals();
    }

    public function setRequest(ServerRequestInterface $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function clearRequest(): self
    {
        $this->request = null;

        return $this;
    }

    public function getUserIp(): ?string
    {
        $serverParams = $this->getRequest()->getServerParams();
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'REMOTE_ADDR',
        ];

        foreach ($keys as $key) {
            if (!empty($serverParams[$key]) && \is_string($serverParams[$key])) {
                $ips = explode(',', $serverParams[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    public function getUrl(): ?string
    {
        $uri = $this->getRequest()->getUri();
        $host = $uri->getHost();
        if ($host === '') {
            return null;
        }

        $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : 'http';

        return (string)$uri->withScheme($scheme)->withHost($host);
    }

    public function getHeaders(): ?array
    {
        $headers = $this->getRequest()->getHeaders();

        if (empty($headers)) {
            return null;
        }

        $result = [];
        foreach ($headers as $name => $values) {
            $result[$name] = implode(', ', $values);
        }

        /** @var array<string, string>|null $result */
        return $this->sanitizer->sanitizeHeaders($result);
    }

    public function getMethod(): ?string
    {
        $method = $this->getRequest()->getMethod();

        return $method !== '' ? $method : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getQueryParams(): ?array
    {
        $queryParams = $this->getRequest()->getQueryParams();
        if (empty($queryParams)) {
            return null;
        }
        /** @var array<string, mixed> $queryParams */
        return $this->maskSensitiveData($queryParams);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBodyParams(): ?array
    {
        $body = (string)$this->getRequest()->getBody();
        if ($body === '' && !empty($_POST)) {
            /** @var array<string, mixed> $_POST */
            return $this->maskSensitiveData($_POST);
        }

        $contentType = strtolower($this->getRequest()->getHeaderLine('Content-Type'));
        if (strpos($contentType, 'application/json') !== false) {
            /** @var mixed $decoded */
            $decoded = json_decode($body, true);
            if (!\is_array($decoded) || empty($decoded)) {
                return null;
            }
            /** @var array<string, mixed> $decoded */
            return $this->maskSensitiveData($decoded);
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            $parsed = [];
            parse_str($body, $parsed);
            if (empty($parsed)) {
                return null;
            }
            /** @var array<string, mixed> $parsed */
            return $this->maskSensitiveData($parsed);
        }

        if (strpos($contentType, 'multipart/form-data') !== false && !empty($_POST)) {
            /** @var array<string, mixed> $_POST */
            return $this->maskSensitiveData($_POST);
        }

        return null;
    }

    public function getCookies(): ?array
    {
        return !empty($_COOKIE) ? $this->maskSensitiveData($_COOKIE) : null;
    }

    /** @return array<string, mixed>|null */
    public function getSession(): ?array
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            return null;
        }

        return !empty($_SESSION) ? $this->maskSensitiveData($_SESSION) : null;
    }

    public function getFiles(): ?array
    {
        return !empty($_FILES) ? $this->maskSensitiveData($_FILES) : null;
    }

    public function getEnv(): ?array
    {
        return !empty($_ENV) ? $this->maskSensitiveData($_ENV) : null;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return [
            'ip' => $this->getUserIp(),
            'url' => $this->getUrl(),
            'method' => $this->getMethod(),
            'headers' => $this->getHeaders(),
            'queryParams' => $this->getQueryParams(),
            'bodyParams' => $this->getBodyParams(),
            'cookies' => $this->getCookies(),
            'session' => $this->getSession(),
            'files' => $this->getFiles(),
            'env' => $this->getEnv(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function maskSensitiveData(array $data): array
    {
        /** @var array<string, mixed> $sanitized */
        $sanitized = $this->sanitizer->sanitizeMap($data);

        return $sanitized ?: [];
    }
}
