<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Psr15;

use DuckBug\Duck;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
        $transaction = $duck->startTransaction(
            $request->getMethod() . ' ' . $request->getUri()->getPath(),
            'http.server'
        );
        $transaction->setContext([
            'route' => $request->getUri()->getPath(),
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
                'route' => $request->getUri()->getPath(),
                'requestMethod' => $request->getMethod(),
            ], false, 'psr15_middleware');
            $transaction->finish('internal_error');
            $duck->captureTransaction($transaction);

            throw $exception;
        } finally {
            $scope->setTransaction($previousTransaction);
            $scope->setTrace($previousTraceId, $previousSpanId);
            $pond->clearRequest();
        }
    }
}
