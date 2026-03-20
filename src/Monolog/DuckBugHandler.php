<?php

declare(strict_types=1);

namespace DuckBug\Monolog;

use DuckBug\Duck;
use Monolog\Handler\AbstractProcessingHandler;
use Throwable;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 * @psalm-suppress MoreSpecificImplementedParamType
 */
final class DuckBugHandler extends AbstractProcessingHandler
{
    public function close(): void
    {
        Duck::get()->flush();

        parent::close();
    }

    /**
     * @param array<string, mixed> $record
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    protected function write(array $record): void
    {
        $duck = Duck::get();
        $rawContext = isset($record['context']) && \is_array($record['context']) ? $record['context'] : [];
        /** @var array<string, mixed> $context */
        $context = $rawContext;

        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            unset($context['exception']);
            $duck->captureException($exception, $context, true, 'monolog');
            return;
        }

        $duck->log(
            isset($record['level_name']) ? (string)$record['level_name'] : 'INFO',
            isset($record['message']) ? (string)$record['message'] : '',
            $context
        );
    }
}
