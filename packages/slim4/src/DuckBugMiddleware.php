<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Slim4;

use DuckBug\Duck;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;
use Throwable;

final class DuckBugMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $duck = Duck::get();
        $pond = Duck::getPond();
        $pond->setRequest($request);
        $scope = $duck->getScope();
        $previousTransaction = $scope->getTransaction();
        $previousTraceId = $scope->getTraceId();
        $previousSpanId = $scope->getSpanId();
        $routePattern = $this->resolveRoutePattern($request);
        $transaction = $duck->startTransaction(
            $request->getMethod() . ' ' . $routePattern,
            'http.server'
        );
        $transaction->setContext([
            'route' => $routePattern,
            'requestMethod' => $request->getMethod(),
        ]);
        $scope->setTransaction($transaction->getName());
        $scope->setTrace($transaction->getTraceId(), $transaction->getSpanId());

        try {
            $response = $handler->handle($request);
            $transaction
                ->addMeasurement('http.response.status_code', $response->getStatusCode(), 'code')
                ->finish($response->getStatusCode() >= 500 ? 'internal_error' : 'ok');
            $duck->captureTransaction($transaction);

            return $response;
        } catch (Throwable $exception) {
            $duck->captureException($exception, [
                'route' => $routePattern,
                'requestMethod' => $request->getMethod(),
            ], false, 'slim4_middleware');
            $transaction->finish('internal_error');
            $duck->captureTransaction($transaction);

            throw $exception;
        } finally {
            $scope->setTransaction($previousTransaction);
            $scope->setTrace($previousTraceId, $previousSpanId);
            $pond->clearRequest();
        }
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
