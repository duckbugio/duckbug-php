<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Drupal;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DuckBugExceptionSubscriber implements EventSubscriberInterface
{
    /** @var RequestStack */
    private $requestStack;

    /** @var AccountProxyInterface */
    private $currentUser;

    public function __construct(RequestStack $requestStack, AccountProxyInterface $currentUser)
    {
        $this->requestStack = $requestStack;
        $this->currentUser = $currentUser;
    }

    public function onException(ExceptionEvent $event): void
    {
        $duck = DrupalBootstrap::boot();
        if ($duck === null) {
            return;
        }

        $request = $event->getRequest();
        DrupalBootstrap::synchronizeScope($duck, $request, $this->currentUser);

        $duck->captureException(
            $event->getThrowable(),
            DrupalContext::buildEventContext('kernel.exception', [
                'route' => $request->attributes->get('_route'),
                'path' => $request->getPathInfo(),
                'method' => $request->getMethod(),
            ]),
            false,
            'drupal_exception_subscriber'
        );
    }

    /**
     * @return array<string, array<int, array<int, int|string>>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onException', 100],
            ],
        ];
    }
}
