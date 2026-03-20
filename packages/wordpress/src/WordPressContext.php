<?php

declare(strict_types=1);

namespace DuckBug\Integrations\WordPress;

final class WordPressContext
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
            'extra' => self::buildWordPressExtra(),
            'transaction' => self::buildTransactionName(),
            'requestId' => self::buildRequestId(),
        ];
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public static function buildHookContext(string $hook, array $details = []): array
    {
        $context = [
            'dTags' => self::buildTags($hook),
            'wordpress' => self::stripEmptyValues($details),
        ];

        return self::stripEmptyValues($context);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildWordPressExtra(): array
    {
        $extra = [
            'version' => self::callStringFunction('get_bloginfo', ['version']),
            'siteName' => self::callStringFunction('get_bloginfo', ['name']),
            'homeUrl' => self::callStringFunction('home_url'),
            'siteUrl' => self::callStringFunction('site_url'),
            'isMultisite' => self::callBoolFunction('is_multisite'),
            'blogId' => self::callIntFunction('get_current_blog_id'),
            'requestUri' => isset($_SERVER['REQUEST_URI']) && \is_string($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
            'isAdmin' => self::callBoolFunction('is_admin'),
            'isAjax' => self::callBoolFunction('wp_doing_ajax'),
            'isCron' => self::callBoolFunction('wp_doing_cron'),
            'isRest' => \defined('REST_REQUEST') && REST_REQUEST === true,
        ];

        return self::stripEmptyValues($extra);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function buildUserContext(): ?array
    {
        if (!\function_exists('is_user_logged_in') || !is_user_logged_in()) {
            return null;
        }

        if (!\function_exists('wp_get_current_user')) {
            return null;
        }

        $user = wp_get_current_user();
        if (!\is_object($user)) {
            return null;
        }

        $context = [];

        if (isset($user->ID) && is_scalar($user->ID) && trim((string)$user->ID) !== '') {
            $context['id'] = trim((string)$user->ID);
        }

        if (isset($user->user_login) && is_scalar($user->user_login) && trim((string)$user->user_login) !== '') {
            $context['username'] = trim((string)$user->user_login);
        }

        if (isset($user->user_email) && is_scalar($user->user_email) && trim((string)$user->user_email) !== '') {
            $context['email'] = trim((string)$user->user_email);
        }

        if (isset($user->display_name) && is_scalar($user->display_name) && trim((string)$user->display_name) !== '') {
            $context['name'] = trim((string)$user->display_name);
        }

        return !empty($context) ? $context : null;
    }

    /**
     * @return string[]
     */
    private static function buildTags(string $hook): array
    {
        $tags = ['framework:wordpress'];

        $hook = trim($hook);
        if ($hook !== '') {
            $tags[] = 'hook:' . $hook;
        }

        $version = self::callStringFunction('get_bloginfo', ['version']);
        if ($version !== null) {
            $tags[] = 'wp:' . $version;
        }

        $blogId = self::callIntFunction('get_current_blog_id');
        if ($blogId !== null) {
            $tags[] = 'blog:' . $blogId;
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

    /**
     * @param list<mixed> $args
     */
    private static function callStringFunction(string $function, array $args = []): ?string
    {
        if (!\function_exists($function)) {
            return null;
        }

        /** @var mixed $value */
        $value = \call_user_func_array($function, $args);
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private static function callBoolFunction(string $function): ?bool
    {
        if (!\function_exists($function)) {
            return null;
        }

        /** @var mixed $value */
        $value = \call_user_func($function);

        return \is_bool($value) ? $value : null;
    }

    private static function callIntFunction(string $function): ?int
    {
        if (!\function_exists($function)) {
            return null;
        }

        /** @var mixed $value */
        $value = \call_user_func($function);

        return \is_int($value) ? $value : null;
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
