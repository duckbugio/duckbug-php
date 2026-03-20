<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Drupal;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use DuckBug\Core\ProviderSetup;
use DuckBug\Duck;
use DuckBug\Providers\DuckBugProvider;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class DrupalBootstrap
{
    /** @var bool */
    private static $booted = false;

    public static function boot(array $overrides = []): ?Duck
    {
        $config = self::resolveConfig($overrides);
        if (!self::boolValue($config, 'enabled', true)) {
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

            $privacy = self::privacyValue($config);
            if (!empty($privacy)) {
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

    public static function synchronizeScope(Duck $duck, ?Request $request = null, ?AccountInterface $account = null): void
    {
        $scopeData = DrupalContext::buildScopeData($request, $account);
        $extra = $duck->getScope()->getExtra();

        $duck
            ->setTag('framework', 'drupal')
            ->setService($duck->getScope()->getService() !== null ? $duck->getScope()->getService() : 'drupal')
            ->setExtra(array_merge($extra, ['drupal' => $scopeData['extra']]));

        if ($scopeData['user'] !== null) {
            $duck->setUser($scopeData['user']);
        } else {
            $duck->clearUser();
        }

        $duck->setTransaction($scopeData['transaction']);
        $duck->setRequestId($scopeData['requestId']);

        if ($duck->getScope()->getServerName() === null && $request !== null) {
            $host = trim((string)$request->getHost());
            if ($host !== '') {
                $duck->setServerName($host);
            }
        }
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
            $duck->setService('drupal');
        }

        if ($environment !== null) {
            $duck->setEnvironment($environment);
        }

        if ($release !== null) {
            $duck->setRelease($release);
        }

        if ($serverName !== null) {
            $duck->setServerName($serverName);
        }

        $duck
            ->setSDK(['name' => 'duckbug-drupal'])
            ->setRuntime(['framework' => 'drupal']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function resolveConfig(array $overrides): array
    {
        $config = [
            'enabled' => true,
            'dsn' => self::constantValue(['DUCKBUG_DRUPAL_DSN', 'DUCKBUG_DSN']),
            'environment' => self::constantValue(['DUCKBUG_DRUPAL_ENVIRONMENT', 'DUCKBUG_ENVIRONMENT']),
            'release' => self::constantValue(['DUCKBUG_DRUPAL_RELEASE', 'DUCKBUG_RELEASE']),
            'service' => self::constantValue(['DUCKBUG_DRUPAL_SERVICE', 'DUCKBUG_SERVICE']),
            'serverName' => self::constantValue(['DUCKBUG_DRUPAL_SERVER_NAME', 'DUCKBUG_SERVER_NAME']),
            'enableEnvLogging' => self::constantValue(['DUCKBUG_DRUPAL_ENABLE_ENV_LOGGING', 'DUCKBUG_ENABLE_ENV_LOGGING'], false),
            'enableRequestContextLogging' => self::constantValue(
                ['DUCKBUG_DRUPAL_ENABLE_REQUEST_CONTEXT_LOGGING', 'DUCKBUG_ENABLE_REQUEST_CONTEXT_LOGGING'],
                true
            ),
            'timeout' => self::constantValue(['DUCKBUG_DRUPAL_TIMEOUT', 'DUCKBUG_TIMEOUT'], 5),
            'connectionTimeout' => self::constantValue(['DUCKBUG_DRUPAL_CONNECTION_TIMEOUT', 'DUCKBUG_CONNECTION_TIMEOUT'], 3),
            'batchSize' => self::constantValue(['DUCKBUG_DRUPAL_BATCH_SIZE', 'DUCKBUG_BATCH_SIZE'], 1),
            'maxRetries' => self::constantValue(['DUCKBUG_DRUPAL_MAX_RETRIES', 'DUCKBUG_MAX_RETRIES'], 2),
            'retryDelayMs' => self::constantValue(['DUCKBUG_DRUPAL_RETRY_DELAY_MS', 'DUCKBUG_RETRY_DELAY_MS'], 100),
            'sensitiveFields' => self::constantValue(['DUCKBUG_DRUPAL_SENSITIVE_FIELDS'], []),
            'privacy' => self::constantValue(['DUCKBUG_DRUPAL_PRIVACY'], []),
        ];

        $settings = Settings::get('duckbug', []);
        if (\is_array($settings)) {
            $config = array_replace_recursive($config, $settings);
        }

        return array_replace_recursive($config, $overrides);
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
    private static function privacyValue(array $config): array
    {
        if (!isset($config['privacy']) || !\is_array($config['privacy'])) {
            return [];
        }

        $result = [];

        foreach ($config['privacy'] as $name => $value) {
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
