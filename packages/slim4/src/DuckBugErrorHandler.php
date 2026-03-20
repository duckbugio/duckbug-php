<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Slim4;

use DuckBug\Duck;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

final class DuckBugErrorHandler
{
    /** @var callable */
    private $next;

    public function __construct(callable $next)
    {
        $this->next = $next;
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        try {
            $duck = Duck::get();
            $pond = Duck::getPond();
            $pond->setRequest($request);

            try {
                $duck->captureException($exception, [
                    'route' => $this->resolveRoutePattern($request),
                    'requestMethod' => $request->getMethod(),
                ], false, 'slim4_error_handler');
                $duck->flush();
            } finally {
                $pond->clearRequest();
            }
        } catch (Throwable $ignored) {
        }

        $next = $this->next;

        return $next($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails);
    }

    private function resolveRoutePattern(ServerRequestInterface $request): string
    {
        try {
            $route = RouteContext::fromRequest($request)->getRoute();
            if ($route !== null) {
                $pattern = trim((string)$route->getPattern());
                if ($pattern !== '') {
                    return $pattern;
                }
            }
        } catch (Throwable $ignored) {
        }

        $path = trim($request->getUri()->getPath());

        return $path !== '' ? $path : '/';
    }
}
