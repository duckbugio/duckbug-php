<?php

declare(strict_types=1);

namespace DuckBug\Integrations\WordPress;

use DuckBug\Core\ProviderSetup;
use DuckBug\Duck;
use DuckBug\Providers\DuckBugProvider;
use Throwable;

final class WordPressBootstrap
{
    /** @var bool */
    private static $booted = false;

    public static function boot(array $overrides = []): ?Duck
    {
        $config = self::resolveConfig($overrides);
        if (!self::isEnabled($config)) {
            return null;
        }

        $dsn = self::stringValue($config, 'dsn');
        if ($dsn === null) {
            return null;
        }

        try {
            $duck = Duck::get();
        } catch (Throwable $exception) {
            $provider = DuckBugProvider::create(
                $dsn,
                self::boolValue($config, 'enableEnvLogging', false),
                self::boolValue($config, 'enableRequestContextLogging', true),
                self::intValue($config, 'timeout', 5),
                self::intValue($config, 'connectionTimeout', 3),
                self::intValue($config, 'batchSize', 1),
                self::intValue($config, 'maxRetries', 2),
                self::intValue($config, 'retryDelayMs', 100)
            );

            $privacy = self::arrayValue($config, 'privacy');
            if (!empty($privacy)) {
                /** @var array<string, bool> $privacy */
                $provider->configurePrivacy($privacy);
            }

            if (isset($config['beforeSend']) && \is_callable($config['beforeSend'])) {
                $provider->setBeforeSend($config['beforeSend']);
            }

            if (isset($config['transportFailureHandler']) && \is_callable($config['transportFailureHandler'])) {
                $provider->setTransportFailureHandler($config['transportFailureHandler']);
            }

            $duck = Duck::wake(
                [new ProviderSetup($provider)],
                self::stringListValue(
                    $config,
                    'sensitiveFields',
                    ['password', 'token', 'api_key', 'authorization', 'cookie', 'session', 'secret']
                )
            );
        }

        self::configureDuck($duck, $config);
        self::$booted = true;

        return $duck;
    }

    public static function registerHooks(array $overrides = []): ?Duck
    {
        $duck = self::boot($overrides);
        if ($duck === null) {
            return null;
        }

        self::synchronizeScope($duck);
        WordPressHooks::register($duck);

        return $duck;
    }

    public static function synchronizeScope(Duck $duck): void
    {
        $scopeData = WordPressContext::buildScopeData();
        $extra = $duck->getScope()->getExtra();

        $duck
            ->setTag('framework', 'wordpress')
            ->setService($duck->getScope()->getService() !== null ? $duck->getScope()->getService() : 'wordpress')
            ->setExtra(array_merge($extra, ['wordpress' => $scopeData['extra']]));

        if ($scopeData['user'] !== null) {
            $duck->setUser($scopeData['user']);
        } else {
            $duck->clearUser();
        }

        $duck->setTransaction($scopeData['transaction']);
        $duck->setRequestId($scopeData['requestId']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function configureDuck(Duck $duck, array $config): void
    {
        $service = self::stringValue($config, 'service');
        $environment = self::stringValue($config, 'environment');
        $release = self::stringValue($config, 'release');
        $serverName = self::stringValue($config, 'serverName');

        if ($service !== null) {
            $duck->setService($service);
        } elseif ($duck->getScope()->getService() === null) {
            $duck->setService('wordpress');
        }

        if ($environment !== null) {
            $duck->setEnvironment($environment);
        }

        if ($release !== null) {
            $duck->setRelease($release);
        }

        if ($serverName !== null) {
            $duck->setServerName($serverName);
        } elseif (\function_exists('home_url')) {
            $host = parse_url((string)home_url(), PHP_URL_HOST);
            if (\is_string($host) && trim($host) !== '') {
                $duck->setServerName($host);
            }
        }

        $duck
            ->setSDK(['name' => 'duckbug-wordpress'])
            ->setRuntime(['framework' => 'wordpress']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function resolveConfig(array $overrides): array
    {
        $config = [
            'enabled' => true,
            'dsn' => self::constantValue(['DUCKBUG_WORDPRESS_DSN', 'DUCKBUG_DSN']),
            'environment' => self::constantValue(['DUCKBUG_WORDPRESS_ENVIRONMENT', 'DUCKBUG_ENVIRONMENT']),
            'release' => self::constantValue(['DUCKBUG_WORDPRESS_RELEASE', 'DUCKBUG_RELEASE']),
            'service' => self::constantValue(['DUCKBUG_WORDPRESS_SERVICE', 'DUCKBUG_SERVICE']),
            'serverName' => self::constantValue(['DUCKBUG_WORDPRESS_SERVER_NAME', 'DUCKBUG_SERVER_NAME']),
            'enableEnvLogging' => self::constantValue(['DUCKBUG_WORDPRESS_ENABLE_ENV_LOGGING', 'DUCKBUG_ENABLE_ENV_LOGGING'], false),
            'enableRequestContextLogging' => self::constantValue(
                ['DUCKBUG_WORDPRESS_ENABLE_REQUEST_CONTEXT_LOGGING', 'DUCKBUG_ENABLE_REQUEST_CONTEXT_LOGGING'],
                true
            ),
            'timeout' => self::constantValue(['DUCKBUG_WORDPRESS_TIMEOUT', 'DUCKBUG_TIMEOUT'], 5),
            'connectionTimeout' => self::constantValue(
                ['DUCKBUG_WORDPRESS_CONNECTION_TIMEOUT', 'DUCKBUG_CONNECTION_TIMEOUT'],
                3
            ),
            'batchSize' => self::constantValue(['DUCKBUG_WORDPRESS_BATCH_SIZE', 'DUCKBUG_BATCH_SIZE'], 1),
            'maxRetries' => self::constantValue(['DUCKBUG_WORDPRESS_MAX_RETRIES', 'DUCKBUG_MAX_RETRIES'], 2),
            'retryDelayMs' => self::constantValue(['DUCKBUG_WORDPRESS_RETRY_DELAY_MS', 'DUCKBUG_RETRY_DELAY_MS'], 100),
            'sensitiveFields' => self::constantValue(['DUCKBUG_WORDPRESS_SENSITIVE_FIELDS'], []),
            'privacy' => self::constantValue(['DUCKBUG_WORDPRESS_PRIVACY'], []),
        ];

        $constantConfig = self::constantValue(['DUCKBUG_WORDPRESS_CONFIG'], []);
        if (\is_array($constantConfig)) {
            $config = array_replace_recursive($config, $constantConfig);
        }

        if (\function_exists('apply_filters')) {
            /** @var mixed $filtered */
            $filtered = apply_filters('duckbug_wordpress_config', $config);
            if (\is_array($filtered)) {
                $config = array_replace_recursive($config, $filtered);
            }
        }

        return array_replace_recursive($config, $overrides);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function isEnabled(array $config): bool
    {
        return self::boolValue($config, 'enabled', true);
    }

    /**
     * @param list<string> $names
     * @param mixed|null $default
     * @return mixed
     */
    private static function constantValue(array $names, $default = null)
    {
        foreach ($names as $name) {
            if (\defined($name)) {
                return \constant($name);
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function stringValue(array $config, string $key): ?string
    {
        if (!isset($config[$key]) || !is_scalar($config[$key])) {
            return null;
        }

        $value = trim((string)$config[$key]);

        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function boolValue(array $config, string $key, bool $default): bool
    {
        if (!isset($config[$key])) {
            return $default;
        }

        $value = $config[$key];
        if (\is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool)$value;
        }

        if (\is_string($value)) {
            $normalized = strtolower(trim($value));

            return !\in_array($normalized, ['0', 'false', 'off', 'no', ''], true);
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function intValue(array $config, string $key, int $default): int
    {
        if (!isset($config[$key]) || !is_numeric($config[$key])) {
            return $default;
        }

        return (int)$config[$key];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, bool>
     */
    private static function arrayValue(array $config, string $key): array
    {
        if (!isset($config[$key]) || !\is_array($config[$key])) {
            return [];
        }

        $result = [];

        foreach ($config[$key] as $name => $value) {
            if (!\is_string($name) || !\is_bool($value)) {
                continue;
            }

            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     * @param string[] $default
     * @return string[]
     */
    private static function stringListValue(array $config, string $key, array $default): array
    {
        if (!isset($config[$key]) || !\is_array($config[$key])) {
            return $default;
        }

        $result = [];

        foreach ($config[$key] as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $value = trim((string)$value);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return !empty($result) ? array_values(array_unique($result)) : $default;
    }
}
