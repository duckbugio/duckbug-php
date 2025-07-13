<?php

declare(strict_types=1);

namespace DuckBug\Util;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;

final class Pond
{
    /** @var string[] */
    private $sensitiveFields;

    /** @param string[] $sensitiveFields */
    private function __construct(array $sensitiveFields)
    {
        $this->sensitiveFields = $sensitiveFields;
    }

    /** @param string[] $sensitiveFields */
    public static function ripple(array $sensitiveFields): self
    {
        return new self($sensitiveFields);
    }

    public function getRequest(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        return $creator->fromGlobals();
    }

    public function getUserIp(): ?string
    {
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
            if (!empty($_SERVER[$key]) && \is_string($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
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
        if (empty($_SERVER['HTTP_HOST'])) {
            return null;
        }

        return ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . ($_SERVER['REQUEST_URI'] ?? '');
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

        return $result;
    }

    public function getMethod(): ?string
    {
        return $_SERVER['REQUEST_METHOD'] ?? null;
    }

    public function getQueryParams(): ?array
    {
        $queryParams = $this->getRequest()->getQueryParams();
        return !empty($queryParams) ? $this->maskSensitiveData($queryParams) : null;
    }

    public function getBodyParams(): ?array
    {
        $bodyParams = (array)json_decode($this->getRequest()->getBody()->getContents(), true);
        return !empty($bodyParams) ? $this->maskSensitiveData($bodyParams) : null;
    }

    public function getCookies(): ?array
    {
        return !empty($_COOKIE) ? $_COOKIE : null;
    }

    /** @return array<non-empty-string, mixed>|null */
    public function getSession(): ?array
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            return null;
        }

        return !empty($_SESSION) ? $_SESSION : null;
    }

    public function getFiles(): ?array
    {
        return !empty($_FILES) ? $_FILES : null;
    }

    public function getEnv(): ?array
    {
        return !empty($_ENV) ? $this->maskSensitiveData($_ENV) : null;
    }

    private function maskSensitiveData(array $data): array
    {
        /** @var array<int|string, array|bool|float|int|string> $data */
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif (
                \is_string($key) &&
                \in_array(strtolower($key), array_map('strtolower', $this->sensitiveFields), true)
            ) {
                $data[$key] = '***';
            }
        }

        return $data;
    }
}
