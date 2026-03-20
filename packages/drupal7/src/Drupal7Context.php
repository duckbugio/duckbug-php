<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Drupal7;

final class Drupal7Context
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
    public static function buildScopeData(): array
    {
        return [
            'user' => self::buildUserContext(),
            'extra' => self::buildDrupal7Extra(),
            'transaction' => self::buildTransactionName(),
            'requestId' => self::buildRequestId(),
        ];
    }

    /**
     * @param array<string, mixed> $logEntry
     * @return array<string, mixed>
     */
    public static function buildWatchdogContext(array $logEntry): array
    {
        $type = isset($logEntry['type']) && is_scalar($logEntry['type']) ? trim((string)$logEntry['type']) : null;

        $context = [
            'dTags' => self::buildTags('watchdog', $type),
            'drupal7' => self::stripEmptyValues([
                'type' => $type,
                'severity' => isset($logEntry['severity']) && is_scalar($logEntry['severity']) ? (string)$logEntry['severity'] : null,
                'requestUri' => isset($logEntry['request_uri']) && is_scalar($logEntry['request_uri']) ? (string)$logEntry['request_uri'] : null,
                'referer' => isset($logEntry['referer']) && is_scalar($logEntry['referer']) ? (string)$logEntry['referer'] : null,
                'ip' => isset($logEntry['ip']) && is_scalar($logEntry['ip']) ? (string)$logEntry['ip'] : null,
                'link' => isset($logEntry['link']) && is_scalar($logEntry['link']) ? (string)$logEntry['link'] : null,
                'variables' => isset($logEntry['variables']) && \is_array($logEntry['variables']) ? $logEntry['variables'] : [],
            ]),
        ];

        return self::stripEmptyValues($context);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buildUserContext(): ?array
    {
        if (!isset($GLOBALS['user']) || !\is_object($GLOBALS['user'])) {
            return null;
        }

        $user = $GLOBALS['user'];
        $uid = isset($user->uid) && is_scalar($user->uid) ? trim((string)$user->uid) : '';
        if ($uid === '' || $uid === '0') {
            return null;
        }

        $context = [
            'id' => $uid,
            'username' => isset($user->name) && is_scalar($user->name) ? trim((string)$user->name) : null,
            'email' => isset($user->mail) && is_scalar($user->mail) ? trim((string)$user->mail) : null,
        ];

        $context = self::stripEmptyValues($context);

        return !empty($context) ? $context : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildDrupal7Extra(): array
    {
        $extra = [
            'siteName' => self::variableValue('site_name'),
            'requestUri' => isset($_SERVER['REQUEST_URI']) && \is_string($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
            'path' => \function_exists('current_path') ? current_path() : null,
            'baseUrl' => \function_exists('url') && \function_exists('current_path')
                ? url(current_path(), ['absolute' => true])
                : null,
            'ip' => isset($_SERVER['REMOTE_ADDR']) && \is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
            'language' => isset($GLOBALS['language']) && \is_object($GLOBALS['language']) && isset($GLOBALS['language']->language)
                ? trim((string)$GLOBALS['language']->language)
                : null,
            'pathAlias' => \function_exists('drupal_get_path_alias') && \function_exists('current_path')
                ? drupal_get_path_alias(current_path())
                : null,
        ];

        return self::stripEmptyValues($extra);
    }

    /**
     * @return string[]
     */
    private static function buildTags(string $hook, ?string $type = null): array
    {
        $tags = ['framework:drupal7', 'hook:' . trim($hook)];

        if ($type !== null && trim($type) !== '') {
            $tags[] = 'type:' . trim($type);
        }

        return $tags;
    }

    private static function buildTransactionName(): ?string
    {
        $method = isset($_SERVER['REQUEST_METHOD']) && \is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper(trim($_SERVER['REQUEST_METHOD']))
            : '';
        $uri = isset($_SERVER['REQUEST_URI']) && \is_string($_SERVER['REQUEST_URI'])
            ? trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
            : '';

        if ($method === '' && $uri === '') {
            return null;
        }

        return trim($method . ' ' . ($uri !== '' ? $uri : '/'));
    }

    private static function buildRequestId(): ?string
    {
        $candidates = [
            'HTTP_X_REQUEST_ID',
            'HTTP_X_CORRELATION_ID',
            'HTTP_X_AMZN_TRACE_ID',
        ];

        foreach ($candidates as $key) {
            if (isset($_SERVER[$key]) && \is_string($_SERVER[$key])) {
                $value = trim($_SERVER[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private static function variableValue(string $name): ?string
    {
        if (!\function_exists('variable_get')) {
            return null;
        }

        /** @var mixed $value */
        $value = variable_get($name, null);
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
