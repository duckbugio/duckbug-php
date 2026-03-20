<?php

declare(strict_types=1);

namespace DuckBug\Integrations;

use DuckBug\Core\Client;
use DuckBug\Duck;
use Throwable;

final class ErrorHandlerIntegration
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: Duck::get()->getClient();
    }

    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $this->client->captureError($severity, $message, $file, $line, [], false, 'error_handler');

        return false;
    }

    public function handleException(Throwable $exception): void
    {
        $this->client->captureException($exception, [], false, 'exception_handler');
        $this->client->flush();
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if (!\is_array($error)) {
            $this->client->flush();
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (\in_array($error['type'], $fatalTypes, true)) {
            $this->client->captureError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line'],
                [],
                false,
                'shutdown'
            );
        }

        $this->client->flush();
    }
}
