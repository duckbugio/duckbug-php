<?php

declare(strict_types=1);

namespace DuckBug;

use DuckBug\Core\ProviderSetup;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Throwable;

class Duck implements LoggerInterface
{
    use LoggerTrait;

    /** @var Duck|null */
    private static $instance;
    private $setups;

    /** @param ProviderSetup[] $setups */
    public function __construct(
        array $setups = []
    ) {
        $this->setups = $setups;
    }

    /** @param ProviderSetup[] $setups */
    public static function wake(array $setups = []): self
    {
        self::$instance = new self($setups);

        return self::$instance;
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
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
