<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Yii2;

use DuckBug\Duck;
use Throwable;
use yii\log\Logger as YiiLogger;
use yii\log\Target;

final class DuckBugTarget extends Target
{
    public function export(): void
    {
        try {
            $duck = Duck::get();
        } catch (Throwable $exception) {
            return;
        }

        foreach ($this->messages as $message) {
            $category = isset($message[2]) && is_scalar($message[2]) ? (string)$message[2] : null;
            $context = DuckBugContext::build($category);
            $exception = $this->extractException($message);

            if ($exception instanceof Throwable) {
                $duck->captureException($exception, $context, true, 'yii2_target');
                continue;
            }

            $duck->log(
                $this->normalizeLevel(isset($message[1]) ? (int)$message[1] : YiiLogger::LEVEL_INFO),
                $this->formatMessage($message),
                $context
            );
        }
    }

    /**
     * @param array<int, mixed> $message
     */
    private function extractException(array $message): ?Throwable
    {
        $value = $message[0] ?? null;

        return $value instanceof Throwable ? $value : null;
    }

    private function normalizeLevel(int $level): string
    {
        switch ($level) {
            case YiiLogger::LEVEL_TRACE:
            case YiiLogger::LEVEL_PROFILE:
                return 'DEBUG';
            case YiiLogger::LEVEL_WARNING:
                return 'WARNING';
            case YiiLogger::LEVEL_ERROR:
                return 'ERROR';
            case YiiLogger::LEVEL_INFO:
            default:
                return 'INFO';
        }
    }
}
