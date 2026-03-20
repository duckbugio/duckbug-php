<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Yii2;

use DuckBug\Duck;
use Throwable;
use yii\web\ErrorHandler;

final class DuckBugWebErrorHandler extends ErrorHandler
{
    /**
     * @param mixed $exception
     */
    protected function logException($exception)
    {
        $this->capture($exception, 'yii2_web_error_handler');

        parent::logException($exception);
    }

    /**
     * @param mixed $exception
     */
    private function capture($exception, string $mechanism): void
    {
        if (!$exception instanceof Throwable) {
            return;
        }

        try {
            $duck = Duck::get();
            $duck->captureException($exception, DuckBugContext::build(), false, $mechanism);
            $duck->flush();
        } catch (Throwable $ignored) {
        }
    }
}
