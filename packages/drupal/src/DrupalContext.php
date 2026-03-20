<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Drupal;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class DrupalContext
{
    /**
     * @return array<string, mixed>
     * @psalm-return array{
     *     user: array<string, mixed>|null,
     *     extra: array<string, mixed>,
     *     transaction: string|null,
     *     requestId: string|null
     * }
     */
    public static function buildScopeData(?Request $request = null, ?AccountInterface $account = null): array
    {
        return [
            'user' => self::buildUserContext($account),
            'extra' => self::buildDrupalExtra($request, $account),
            'transaction' => self::buildTransactionName($request),
            'requestId' => self::buildRequestId($request),
        ];
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public static function buildEventContext(string $hook, array $details = []): array
    {
        $context = [
            'dTags' => self::buildTags($hook),
            'drupal' => self::stripEmptyValues($details),
        ];

        return self::stripEmptyValues($context);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildDrupalExtra(?Request $request, ?AccountInterface $account): array
    {
        $extra = [
            'version' => \defined('Drupal::VERSION') ? \constant('Drupal::VERSION') : null,
            'route' => $request !== null ? self::stringValue($request->attributes->get('_route')) : null,
            'path' => $request !== null ? self::stringValue($request->getPathInfo()) : null,
            'host' => $request !== null ? self::stringValue($request->getHost()) : null,
            'scheme' => $request !== null ? self::stringValue($request->getScheme()) : null,
            'format' => $request !== null ? self::stringValue($request->getRequestFormat(null)) : null,
            'isAjax' => $request !== null ? $request->isXmlHttpRequest() : null,
            'method' => $request !== null ? self::stringValue($request->getMethod()) : null,
            'uid' => $account !== null ? self::stringValue($account->id()) : null,
        ];

        if ($request !== null) {
            $extra['query'] = $request->query->all();
            $extra['body'] = $request->request->all();
            $extra['headers'] = $request->headers->all();
            $extra['cookies'] = $request->cookies->all();
            $extra['files'] = self::normalizeFiles($request->files->all());
        }

        return self::stripEmptyValues($extra);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buildUserContext(?AccountInterface $account): ?array
    {
        if ($account === null || !$account->isAuthenticated()) {
            return null;
        }

        $context = [
            'id' => self::stringValue($account->id()),
            'username' => self::stringValue($account->getAccountName()),
        ];

        if (method_exists($account, 'getEmail')) {
            /** @var mixed $email */
            $email = $account->getEmail();
            $context['email'] = self::stringValue($email);
        }

        $context = self::stripEmptyValues($context);

        return !empty($context) ? $context : null;
    }

    /**
     * @return string[]
     */
    private static function buildTags(string $hook): array
    {
        $tags = ['framework:drupal'];

        $hook = trim($hook);
        if ($hook !== '') {
            $tags[] = 'hook:' . $hook;
        }

        if (\defined('Drupal::VERSION')) {
            $version = trim((string)\constant('Drupal::VERSION'));
            if ($version !== '') {
                $tags[] = 'drupal:' . $version;
            }
        }

        return $tags;
    }

    private static function buildTransactionName(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $method = trim((string)$request->getMethod());
        $path = trim((string)$request->getPathInfo());

        if ($method === '' && $path === '') {
            return null;
        }

        return trim($method . ' ' . ($path !== '' ? $path : '/'));
    }

    private static function buildRequestId(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $candidates = [
            'X-Request-Id',
            'X-Correlation-Id',
            'X-Amzn-Trace-Id',
        ];

        foreach ($candidates as $name) {
            $value = trim((string)$request->headers->get($name, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<mixed>|UploadedFile> $files
     * @return array<string, mixed>
     */
    private static function normalizeFiles(array $files): array
    {
        $result = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFile) {
                $result[$key] = [
                    'originalName' => $value->getClientOriginalName(),
                    'mimeType' => $value->getClientMimeType(),
                    'size' => $value->getSize(),
                    'error' => $value->getError(),
                ];
                continue;
            }

            if (\is_array($value)) {
                $result[$key] = self::normalizeFiles($value);
            }
        }

        return self::stripEmptyValues($result);
    }

    /**
     * @param mixed $value
     */
    private static function stringValue($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function stripEmptyValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $value = self::stripEmptyValues($value);
            }

            if ($value === null || $value === [] || $value === '') {
                unset($data[$key]);
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }
}
