<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Symfony;

use DuckBug\Duck;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

final class DuckBugExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $duck = Duck::get();
        $request = $event->getRequest();
        $pond = Duck::getPond();
        if ($request instanceof ServerRequestInterface) {
            $pond->setRequest($request);
        }

        try {
            $duck->captureException($event->getThrowable(), [
                'route' => $request->attributes->get('_route'),
            ], false, 'symfony_kernel');
        } finally {
            if ($request instanceof ServerRequestInterface) {
                $pond->clearRequest();
            }
        }
    }
}
