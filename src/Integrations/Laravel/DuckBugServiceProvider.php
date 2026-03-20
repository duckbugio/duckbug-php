<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Laravel;

use DuckBug\Duck;
use DuckBug\Integrations\ErrorHandlerIntegration;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * @psalm-suppress UndefinedClass
 */
final class DuckBugServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $duck = Duck::get();
        } catch (Throwable $exception) {
            return;
        }

        (new ErrorHandlerIntegration($duck->getClient()))->register();
    }
}
