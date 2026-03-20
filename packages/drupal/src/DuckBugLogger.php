<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Drupal;

use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\RequestStack;

final class DuckBugLogger extends AbstractLogger
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

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array<string, mixed> $context
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function log($level, $message, array $context = []): void
    {
        $duck = DrupalBootstrap::boot();
        if ($duck === null) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        DrupalBootstrap::synchronizeScope($duck, $request, $this->currentUser);

        $channel = isset($context['channel']) && is_scalar($context['channel'])
            ? trim((string)$context['channel'])
            : null;

        $duck->log(
            strtoupper(trim((string)$level)),
            (string)$message,
            array_merge(
                DrupalContext::buildEventContext('logger', [
                    'channel' => $channel,
                ]),
                ['drupalLogContext' => $context]
            )
        );
    }
}
