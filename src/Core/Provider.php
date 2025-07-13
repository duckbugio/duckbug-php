<?php

declare(strict_types=1);

namespace DuckBug\Core;

use Psr\Log\LoggerInterface;
use Throwable;

interface Provider extends LoggerInterface
{
    public function quack(Throwable $exception, array $context = []): void;
}
