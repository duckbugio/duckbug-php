<?php

declare(strict_types=1);

namespace DuckBug;

use DuckBug\Core\ProviderSetup;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Throwable;

final class Duck implements LoggerInterface
{
    use LoggerTrait;

    /** @var Duck|null */
    private static $duck;
    /** @var Pond|null */
    private static $pond;
    /** @var ProviderSetup[] */
    private $setups;

    /**
     * @param ProviderSetup[] $setups
     * @param string[] $sensitiveFields
     */
    private function __construct(
        array $setups = [],
        array $sensitiveFields = []
    ) {
        $this->setups = $setups;
        self::$pond = Pond::ripple($sensitiveFields);
    }

    /**
     * @param ProviderSetup[] $setups
     * @param string[] $sensitiveFields
     */
    public static function wake(
        array $setups = [],
        array $sensitiveFields = ['password', 'token', 'api_key']
    ): self {
        self::$duck = new self($setups, $sensitiveFields);

        return self::$duck;
    }

    /**
     * @throws Exception
     */
    public static function get(): self
    {
        if (self::$duck === null) {
            throw new Exception('Duck::get() was called before Duck::wake()');
        }

        return self::$duck;
    }

    public static function getPond(): Pond
    {
        if (self::$pond === null) {
            self::$pond = Pond::ripple();
        }

        return self::$pond;
    }

    public function quack(Throwable $exception, array $context = []): void
    {
        foreach ($this->setups as $setup) {
            if (!$setup->enabledThrowable) {
                continue;
            }

            $setup->provider->quack($exception, $context);
        }
    }

    /**
     * @param mixed $level
     * @param string $message
     */
    public function log($level, $message, array $context = []): void
    {
        foreach ($this->setups as $setup) {
            $isEnabled = false;

            switch (strtolower((string)$level)) {
                case LogLevel::DEBUG:
                    $isEnabled = $setup->enabledDebug;
                    break;
                case LogLevel::INFO:
                    $isEnabled = $setup->enabledInfo;
                    break;
                case LogLevel::NOTICE:
                    $isEnabled = $setup->enabledNotice;
                    break;
                case LogLevel::WARNING:
                    $isEnabled = $setup->enabledWarning;
                    break;
                case LogLevel::ERROR:
                    $isEnabled = $setup->enabledError;
                    break;
                case LogLevel::CRITICAL:
                    $isEnabled = $setup->enabledCritical;
                    break;
                case LogLevel::ALERT:
                    $isEnabled = $setup->enabledAlert;
                    break;
                case LogLevel::EMERGENCY:
                    $isEnabled = $setup->enabledEmergency;
                    break;
            }

            if (!$isEnabled) {
                continue;
            }

            $setup->provider->log($level, $message, $context);
        }
    }
}
