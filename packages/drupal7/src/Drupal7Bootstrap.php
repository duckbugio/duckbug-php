<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Drupal7;

use DuckBug\Core\ProviderSetup;
use DuckBug\Duck;
use DuckBug\Integrations\ErrorHandlerIntegration;
use DuckBug\Providers\DuckBugProvider;
use Throwable;

final class Drupal7Bootstrap
{
    /** @var bool */
    private static $handlersRegistered = false;

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
        self::synchronizeScope($duck);
        self::registerHandlers($duck);

        return $duck;
    }

    /**
     * @param array<string, mixed> $logEntry
     */
    public static function captureWatchdog(array $logEntry): void
    {
        $duck = self::boot();
        if ($duck === null) {
            return;
        }

        self::synchronizeScope($duck);
        $message = isset($logEntry['message']) && is_scalar($logEntry['message'])
            ? trim((string)$logEntry['message'])
            : 'Drupal 7 watchdog event';
        if (\function_exists('format_string') && isset($logEntry['variables']) && \is_array($logEntry['variables'])) {
            /** @var mixed $formatted */
            $formatted = format_string($message, $logEntry['variables']);
            if (is_scalar($formatted) && trim((string)$formatted) !== '') {
                $message = trim((string)$formatted);
            }
        }

        $duck->log(
            self::normalizeWatchdogLevel(isset($logEntry['severity']) ? (int)$logEntry['severity'] : 6),
            $message !== '' ? $message : 'Drupal 7 watchdog event',
            Drupal7Context::buildWatchdogContext($logEntry)
        );
    }

    public static function synchronizeScope(Duck $duck): void
    {
        $scopeData = Drupal7Context::buildScopeData();
        $extra = $duck->getScope()->getExtra();

        $duck
            ->setTag('framework', 'drupal7')
            ->setService($duck->getScope()->getService() !== null ? $duck->getScope()->getService() : 'drupal7')
            ->setExtra(array_merge($extra, ['drupal7' => $scopeData['extra']]));

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
            $duck->setService('drupal7');
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
            ->setSDK(['name' => 'duckbug-drupal7'])
            ->setRuntime(['framework' => 'drupal7']);
    }

    private static function registerHandlers(Duck $duck): void
    {
        if (self::$handlersRegistered) {
            return;
        }

        self::$handlersRegistered = true;
        (new ErrorHandlerIntegration($duck->getClient()))->register();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function resolveConfig(array $overrides): array
    {
        $config = [
            'enabled' => true,
            'dsn' => self::configValue('dsn', ['DUCKBUG_DRUPAL7_DSN', 'DUCKBUG_DSN']),
            'environment' => self::configValue('environment', ['DUCKBUG_DRUPAL7_ENVIRONMENT', 'DUCKBUG_ENVIRONMENT']),
            'release' => self::configValue('release', ['DUCKBUG_DRUPAL7_RELEASE', 'DUCKBUG_RELEASE']),
            'service' => self::configValue('service', ['DUCKBUG_DRUPAL7_SERVICE', 'DUCKBUG_SERVICE']),
            'serverName' => self::configValue('server_name', ['DUCKBUG_DRUPAL7_SERVER_NAME', 'DUCKBUG_SERVER_NAME']),
            'enableEnvLogging' => self::configValue(
                'enable_env_logging',
                ['DUCKBUG_DRUPAL7_ENABLE_ENV_LOGGING', 'DUCKBUG_ENABLE_ENV_LOGGING'],
                false
            ),
            'enableRequestContextLogging' => self::configValue(
                'enable_request_context_logging',
                ['DUCKBUG_DRUPAL7_ENABLE_REQUEST_CONTEXT_LOGGING', 'DUCKBUG_ENABLE_REQUEST_CONTEXT_LOGGING'],
                true
            ),
            'timeout' => self::configValue('timeout', ['DUCKBUG_DRUPAL7_TIMEOUT', 'DUCKBUG_TIMEOUT'], 5),
            'connectionTimeout' => self::configValue(
                'connection_timeout',
                ['DUCKBUG_DRUPAL7_CONNECTION_TIMEOUT', 'DUCKBUG_CONNECTION_TIMEOUT'],
                3
            ),
            'batchSize' => self::configValue('batch_size', ['DUCKBUG_DRUPAL7_BATCH_SIZE', 'DUCKBUG_BATCH_SIZE'], 1),
            'maxRetries' => self::configValue('max_retries', ['DUCKBUG_DRUPAL7_MAX_RETRIES', 'DUCKBUG_MAX_RETRIES'], 2),
            'retryDelayMs' => self::configValue(
                'retry_delay_ms',
                ['DUCKBUG_DRUPAL7_RETRY_DELAY_MS', 'DUCKBUG_RETRY_DELAY_MS'],
                100
            ),
            'sensitiveFields' => self::configValue('sensitive_fields', ['DUCKBUG_DRUPAL7_SENSITIVE_FIELDS'], []),
            'privacy' => self::configValue('privacy', ['DUCKBUG_DRUPAL7_PRIVACY'], []),
        ];

        return array_replace_recursive($config, $overrides);
    }

    /**
     * @param list<string> $constantNames
     * @param mixed|null $default
     * @return mixed
     */
    private static function configValue(string $variableName, array $constantNames, $default = null)
    {
        foreach ($constantNames as $name) {
            if (\defined($name)) {
                return \constant($name);
            }
        }

        if (\function_exists('variable_get')) {
            /** @var mixed $value */
            $value = variable_get('duckbug_' . $variableName, $default);

            return $value;
        }

        if (isset($GLOBALS['conf']['duckbug_' . $variableName])) {
            return $GLOBALS['conf']['duckbug_' . $variableName];
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

    private static function normalizeWatchdogLevel(int $severity): string
    {
        switch ($severity) {
            case 0:
                return 'EMERGENCY';
            case 1:
                return 'ALERT';
            case 2:
                return 'CRITICAL';
            case 3:
                return 'ERROR';
            case 4:
                return 'WARNING';
            case 5:
                return 'NOTICE';
            case 6:
                return 'INFO';
            case 7:
            default:
                return 'DEBUG';
        }
    }
}
