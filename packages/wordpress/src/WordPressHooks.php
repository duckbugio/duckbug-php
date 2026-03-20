<?php

declare(strict_types=1);

namespace DuckBug\Integrations\WordPress;

use DuckBug\Duck;
use DuckBug\Integrations\ErrorHandlerIntegration;
use Throwable;

final class WordPressHooks
{
    /** @var bool */
    private static $registered = false;

    public static function register(Duck $duck): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        (new ErrorHandlerIntegration($duck->getClient()))->register();

        if (!\function_exists('add_action')) {
            return;
        }

        add_action('deprecated_function_run', [self::class, 'onDeprecatedFunction'], 10, 3);
        add_action('deprecated_argument_run', [self::class, 'onDeprecatedArgument'], 10, 3);
        add_action('doing_it_wrong_run', [self::class, 'onDoingItWrong'], 10, 3);
        add_action('wp_mail_failed', [self::class, 'onMailFailed'], 10, 1);
    }

    /**
     * @param mixed $function
     * @param mixed $replacement
     * @param mixed $version
     */
    public static function onDeprecatedFunction($function, $replacement = null, $version = null): void
    {
        $duck = self::duck();
        if ($duck === null) {
            return;
        }

        WordPressBootstrap::synchronizeScope($duck);
        $duck->warning(
            'Deprecated WordPress function used: ' . self::stringify($function, 'unknown'),
            WordPressContext::buildHookContext('deprecated_function_run', [
                'function' => self::stringify($function),
                'replacement' => self::stringify($replacement),
                'version' => self::stringify($version),
            ])
        );
    }

    /**
     * @param mixed $functionName
     * @param mixed $message
     * @param mixed $version
     */
    public static function onDeprecatedArgument($functionName, $message = null, $version = null): void
    {
        $duck = self::duck();
        if ($duck === null) {
            return;
        }

        WordPressBootstrap::synchronizeScope($duck);
        $duck->warning(
            'Deprecated WordPress argument used: ' . self::stringify($functionName, 'unknown'),
            WordPressContext::buildHookContext('deprecated_argument_run', [
                'function' => self::stringify($functionName),
                'message' => self::stringify($message),
                'version' => self::stringify($version),
            ])
        );
    }

    /**
     * @param mixed $functionName
     * @param mixed $message
     * @param mixed $version
     */
    public static function onDoingItWrong($functionName, $message = null, $version = null): void
    {
        $duck = self::duck();
        if ($duck === null) {
            return;
        }

        WordPressBootstrap::synchronizeScope($duck);
        $duck->warning(
            'WordPress reported doing_it_wrong for: ' . self::stringify($functionName, 'unknown'),
            WordPressContext::buildHookContext('doing_it_wrong_run', [
                'function' => self::stringify($functionName),
                'message' => self::stringify($message),
                'version' => self::stringify($version),
            ])
        );
    }

    /**
     * @param mixed $error
     */
    public static function onMailFailed($error): void
    {
        $duck = self::duck();
        if ($duck === null) {
            return;
        }

        WordPressBootstrap::synchronizeScope($duck);

        $details = [
            'type' => \is_object($error) ? \get_class($error) : \gettype($error),
        ];

        if (\is_object($error) && method_exists($error, 'get_error_codes')) {
            /** @var mixed $codes */
            $codes = $error->get_error_codes();
            if (\is_array($codes)) {
                $details['codes'] = $codes;
            }
        }

        if (\is_object($error) && method_exists($error, 'get_error_messages')) {
            /** @var mixed $messages */
            $messages = $error->get_error_messages();
            if (\is_array($messages)) {
                $details['messages'] = $messages;
            }
        }

        if (\is_object($error) && method_exists($error, 'get_all_error_data')) {
            /** @var mixed $data */
            $data = $error->get_all_error_data();
            if (\is_array($data)) {
                $details['data'] = $data;
            }
        }

        $duck->error(
            'WordPress mail delivery failed',
            WordPressContext::buildHookContext('wp_mail_failed', $details)
        );
    }

    private static function duck(): ?Duck
    {
        try {
            return Duck::get();
        } catch (Throwable $exception) {
            return WordPressBootstrap::boot();
        }
    }

    /**
     * @param mixed $value
     */
    private static function stringify($value, ?string $default = null): ?string
    {
        if ($value === null) {
            return $default;
        }

        if (is_scalar($value)) {
            $value = trim((string)$value);

            return $value !== '' ? $value : $default;
        }

        return $default;
    }
}
