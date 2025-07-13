<?php

declare(strict_types=1);

namespace DuckBug\Core;

final class ProviderSetup
{
    /** @var Provider */
    public $provider;
    /** @var bool */
    public $enabledThrowable;
    /** @var bool */
    public $enabledDebug;
    /** @var bool */
    public $enabledInfo;
    /** @var bool */
    public $enabledNotice;
    /** @var bool */
    public $enabledWarning;
    /** @var bool */
    public $enabledError;
    /** @var bool */
    public $enabledCritical;
    /** @var bool */
    public $enabledAlert;
    /** @var bool */
    public $enabledEmergency;

    public function __construct(
        Provider $provider,
        bool $enabledThrowable = true,
        bool $enabledDebug = true,
        bool $enabledInfo = true,
        bool $enabledNotice = true,
        bool $enabledWarning = true,
        bool $enabledError = true,
        bool $enabledCritical = true,
        bool $enabledAlert = true,
        bool $enabledEmergency = true
    ) {
        $this->provider = $provider;
        $this->enabledThrowable = $enabledThrowable;
        $this->enabledDebug = $enabledDebug;
        $this->enabledInfo = $enabledInfo;
        $this->enabledNotice = $enabledNotice;
        $this->enabledWarning = $enabledWarning;
        $this->enabledError = $enabledError;
        $this->enabledCritical = $enabledCritical;
        $this->enabledAlert = $enabledAlert;
        $this->enabledEmergency = $enabledEmergency;
    }
}
